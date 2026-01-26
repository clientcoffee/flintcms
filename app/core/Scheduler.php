<?php

namespace Flint;

/**
 * Lightweight pseudo-cron scheduler for Flint
 *
 * Runs scheduled tasks without requiring server cron configuration.
 * Tasks are checked on each request and executed if due.
 * Uses lock files to prevent concurrent execution.
 */
class Scheduler
{
    private string $lockDir;
    private string $stateDir;
    private array $tasks = [];

    public function __construct(App $app)
    {
        $this->lockDir = $app->root . '/site/submissions/scheduler/locks';
        $this->stateDir = $app->root . '/site/submissions/scheduler/state';

        // Ensure directories exist
        $this->ensureDirectory($this->lockDir);
        $this->ensureDirectory($this->stateDir);
    }

    /**
     * Register a scheduled task
     *
     * @param string $taskId Unique task identifier
     * @param array $schedule Schedule configuration
     * @param callable $callback Function to execute
     */
    public function registerTask(string $taskId, array $schedule, callable $callback): void
    {
        $this->tasks[$taskId] = [
            'schedule' => $schedule,
            'callback' => $callback
        ];
    }

    /**
     * Run scheduler check (called on each request)
     *
     * Lightweight check that only processes due tasks
     */
    public function run(): void
    {
        foreach ($this->tasks as $taskId => $task) {
            if ($this->isTaskDue($taskId, $task['schedule'])) {
                $this->executeTask($taskId, $task['callback']);
            }
        }
    }

    /**
     * Check if a task is due to run
     */
    private function isTaskDue(string $taskId, array $schedule): bool
    {
        // Get last run time
        $stateFile = $this->stateDir . "/{$taskId}.json";
        $lastRun = 0;

        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            $lastRun = $state['last_run'] ?? 0;
        }

        $now = time();
        $scheduleType = $schedule['type'] ?? 'manual';

        // Manual tasks don't auto-run
        if ($scheduleType === 'manual') {
            return false;
        }

        // Hourly: run every hour
        if ($scheduleType === 'hourly') {
            return ($now - $lastRun) >= 3600;
        }

        // Daily: run once per day at specific time
        if ($scheduleType === 'daily') {
            $targetTime = $schedule['time'] ?? '03:00'; // Default: 3 AM
            $lastRunDate = date('Y-m-d', $lastRun);
            $todayDate = date('Y-m-d', $now);

            // Already ran today
            if ($lastRunDate === $todayDate) {
                return false;
            }

            // Check if current time is past target time
            list($hour, $minute) = explode(':', $targetTime);
            $targetTimestamp = strtotime("today {$hour}:{$minute}");

            return $now >= $targetTimestamp;
        }

        // Weekly: run once per week on specific day at specific time
        if ($scheduleType === 'weekly') {
            $targetDay = $schedule['day'] ?? 0; // 0 = Sunday, 6 = Saturday
            $targetTime = $schedule['time'] ?? '03:00';
            $lastRunWeek = date('Y-W', $lastRun);
            $currentWeek = date('Y-W', $now);

            // Already ran this week
            if ($lastRunWeek === $currentWeek) {
                return false;
            }

            // Check if today is the target day
            $currentDay = (int)date('w', $now);
            if ($currentDay !== (int)$targetDay) {
                return false;
            }

            // Check if current time is past target time
            list($hour, $minute) = explode(':', $targetTime);
            $targetTimestamp = strtotime("today {$hour}:{$minute}");

            return $now >= $targetTimestamp;
        }

        // Monthly: run once per month on specific day at specific time
        if ($scheduleType === 'monthly') {
            $targetDay = $schedule['day'] ?? 1; // Day of month (1-28)
            $targetTime = $schedule['time'] ?? '03:00';
            $lastRunMonth = date('Y-m', $lastRun);
            $currentMonth = date('Y-m', $now);

            // Already ran this month
            if ($lastRunMonth === $currentMonth) {
                return false;
            }

            // Check if today is the target day
            $currentDay = (int)date('j', $now);
            if ($currentDay !== (int)$targetDay) {
                return false;
            }

            // Check if current time is past target time
            list($hour, $minute) = explode(':', $targetTime);
            $targetTimestamp = strtotime("today {$hour}:{$minute}");

            return $now >= $targetTimestamp;
        }

        // Interval: run every N seconds
        if ($scheduleType === 'interval') {
            $interval = $schedule['seconds'] ?? 3600;
            return ($now - $lastRun) >= $interval;
        }

        return false;
    }

    /**
     * Execute a scheduled task
     */
    private function executeTask(string $taskId, callable $callback): void
    {
        // Try to acquire lock
        if (!$this->acquireLock($taskId)) {
            // Task is already running
            return;
        }

        try {
            // Execute task callback
            call_user_func($callback);

            // Update last run time
            $this->updateTaskState($taskId, [
                'last_run' => time(),
                'last_status' => 'success'
            ]);
        } catch (\Exception $e) {
            // Log error
            $this->updateTaskState($taskId, [
                'last_run' => time(),
                'last_status' => 'error',
                'last_error' => $e->getMessage()
            ]);
        } finally {
            // Always release lock
            $this->releaseLock($taskId);
        }
    }

    /**
     * Acquire lock for task execution
     */
    private function acquireLock(string $taskId): bool
    {
        $lockFile = $this->lockDir . "/{$taskId}.lock";

        // Check if lock exists and is recent (within 5 minutes)
        if (file_exists($lockFile)) {
            $lockAge = time() - filemtime($lockFile);
            if ($lockAge < 300) {
                // Lock is fresh, task is running
                return false;
            }
            // Lock is stale, remove it
            @unlink($lockFile);
        }

        // Create lock file
        return file_put_contents($lockFile, time(), LOCK_EX) !== false;
    }

    /**
     * Release lock after task execution
     */
    private function releaseLock(string $taskId): void
    {
        $lockFile = $this->lockDir . "/{$taskId}.lock";
        @unlink($lockFile);
    }

    /**
     * Update task state (last run, status, etc.)
     */
    private function updateTaskState(string $taskId, array $state): void
    {
        $stateFile = $this->stateDir . "/{$taskId}.json";
        file_put_contents(
            $stateFile,
            json_encode($state, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * Get task state
     */
    public function getTaskState(string $taskId): ?array
    {
        $stateFile = $this->stateDir . "/{$taskId}.json";
        if (!file_exists($stateFile)) {
            return null;
        }
        return json_decode(file_get_contents($stateFile), true);
    }

    /**
     * Get all scheduled tasks
     */
    public function getTasks(): array
    {
        $result = [];
        foreach ($this->tasks as $taskId => $task) {
            $state = $this->getTaskState($taskId);
            $result[$taskId] = [
                'schedule' => $task['schedule'],
                'state' => $state
            ];
        }
        return $result;
    }

    /**
     * Manually trigger a task (ignores schedule)
     */
    public function triggerTask(string $taskId): bool
    {
        if (!isset($this->tasks[$taskId])) {
            return false;
        }

        $this->executeTask($taskId, $this->tasks[$taskId]['callback']);
        return true;
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0750, true);
        }
    }
}
