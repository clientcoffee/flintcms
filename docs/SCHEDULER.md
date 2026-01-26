# Flint Scheduler

Lightweight pseudo-cron system for scheduling automated tasks without server cron configuration.

## Overview

The Scheduler provides a cron-like task scheduling system that runs entirely within PHP, requiring zero server configuration. It's perfect for shared hosting environments where cron access is limited or unavailable.

### Key Features

- **Zero Configuration**: Works out of the box, no server setup required
- **Hook-Based**: Components register tasks via `register_scheduled_tasks` hook
- **Lightweight**: Fast checks on each request, minimal overhead
- **Lock Protection**: Prevents concurrent execution using file locks
- **Multiple Schedule Types**: Hourly, daily, weekly, monthly, interval, manual
- **Failure Resilient**: Stale locks auto-expire, tasks retry on next check
- **State Tracking**: Records last run time, status, errors for each task

## Quick Start

Register a daily task from a component:

```php
use Flint\BaseComponent;

class Cleanup extends BaseComponent
{
    protected static function registerHooks(): void
    {
        self::registerScheduledTask('daily_cleanup', [
            'type' => 'daily',
            'time' => '03:00',
        ], function (): void {
            // Do cleanup work here.
        });
    }
}
```

## How It Works

### Execution Flow

1. **Request Received**: User visits any page on the site
2. **Scheduler Check**: Lightweight check runs on non-static requests
3. **Task Evaluation**: For each registered task, check if it's due
4. **Lock Acquisition**: Try to acquire lock file for due task
5. **Task Execution**: If lock acquired, run the task callback
6. **State Update**: Record execution time and status
7. **Lock Release**: Remove lock file when done
8. **Continue**: Process continues with normal page load

### Performance Impact

The scheduler is designed to be extremely lightweight:

- **Check Time**: < 1ms for typical task registry
- **File Operations**: Only reads/writes when tasks are due
- **No Blocking**: Task execution happens in-process
- **Smart Skipping**: Skips checks for static assets (CSS, JS, images)

### Long-running tasks

Keep tasks short. Long-running tasks block request processing because tasks run in-process.

## Schedule Types

### Manual (Default)
```php
$scheduler->registerTask('my_task', ['type' => 'manual'], $callback);
```
- Tasks don't auto-run
- Must be triggered manually via admin or API
- Good for user-initiated operations

### Hourly
```php
$scheduler->registerTask('my_task', ['type' => 'hourly'], $callback);
```
- Runs once every hour
- First run happens 1 hour after registration
- Example: Cache clearing, temporary file cleanup

### Daily
```php
$scheduler->registerTask('my_task', [
    'type' => 'daily',
    'time' => '03:00'  // 3 AM
], $callback);
```
- Runs once per day at specified time
- Time in 24-hour format (HH:MM)
- Most common choice for scheduled tasks
- Example: Database backups, report generation

### Weekly
```php
$scheduler->registerTask('my_task', [
    'type' => 'weekly',
    'time' => '03:00',
    'day' => 0  // 0 = Sunday, 6 = Saturday
], $callback);
```
- Runs once per week on specified day
- Day: 0=Sunday, 1=Monday, ..., 6=Saturday
- Example: Weekly report emails, log rotation

### Monthly
```php
$scheduler->registerTask('my_task', [
    'type' => 'monthly',
    'time' => '03:00',
    'day' => 1  // 1st of month
], $callback);
```
- Runs once per month on specified day
- Day: 1-28 (kept to 28 for safety across all months)
- Example: Monthly invoicing, archive cleanup

### Interval
```php
$scheduler->registerTask('my_task', [
    'type' => 'interval',
    'seconds' => 3600  // Every hour
], $callback);
```
- Runs every N seconds
- Flexible for custom timing needs
- Example: API polling, health checks

## Component Integration

### Registering Tasks

Components register tasks via the `register_scheduled_tasks` hook:

```php
namespace Components\MyComponent;

use Flint\BaseComponent;

class MyComponent extends BaseComponent
{
    protected static function registerHooks(): void
    {
        self::registerHook('register_scheduled_tasks', [self::class, 'registerTasks']);
    }

    public static function registerTasks(array $context): void
    {
        $scheduler = $context['scheduler'] ?? null;
        if (!$scheduler) {
            return;
        }

        // Register a daily task
        $scheduler->registerTask('mycomponent_daily_cleanup', [
            'type' => 'daily',
            'time' => '02:00'
        ], function() {
            self::performCleanup();
        });

        // Register an hourly task
        $scheduler->registerTask('mycomponent_cache_refresh', [
            'type' => 'hourly'
        ], function() {
            self::refreshCache();
        });
    }

    private static function performCleanup(): void
    {
        // Task implementation
    }

    private static function refreshCache(): void
    {
        // Task implementation
    }
}
```

### Reading Configuration

Tasks can read component configuration for schedule settings:

```php
public static function registerTasks(array $context): void
{
    $scheduler = $context['scheduler'] ?? null;
    if (!$scheduler) {
        return;
    }

    // Read schedule from site/config.php
    $scheduleType = self::getConfig('mycomponent.schedule', 'manual');

    // Only register if not manual
    if ($scheduleType !== 'manual') {
        $schedule = ['type' => $scheduleType];

        if ($scheduleType === 'daily') {
            $schedule['time'] = self::getConfig('mycomponent.schedule_time', '03:00');
        }

        $scheduler->registerTask('mycomponent_task', $schedule, function() {
            self::runTask();
        });
    }
}
```

## Task State Management

### State Files

Each task's state is stored in `site/submissions/scheduler/state/{task_id}.json`:

```json
{
    "last_run": 1735804800,
    "last_status": "success",
    "last_error": null
}
```

### Lock Files

Lock files live in `site/submissions/scheduler/locks/{task_id}.lock`. Locks expire after 5 minutes, so stale files are safe to delete if a task gets stuck.

### Resetting Scheduler State

- Deleting a **lock** file only unblocks a stuck task.
- Deleting a **state** file resets the task schedule (it will run as if it never ran before).

These files are runtime data and should not be packaged in releases.

### Reading State

```php
$state = $scheduler->getTaskState('my_task_id');
if ($state) {
    $lastRun = $state['last_run'] ?? 0;
    $status = $state['last_status'] ?? 'never';
    $error = $state['last_error'] ?? null;
}
```

### Listing All Tasks

```php
$tasks = $scheduler->getTasks();
foreach ($tasks as $taskId => $task) {
    echo "Task: $taskId\n";
    echo "Schedule: " . $task['schedule']['type'] . "\n";
    if ($task['state']) {
        echo "Last run: " . date('Y-m-d H:i:s', $task['state']['last_run']) . "\n";
        echo "Status: " . $task['state']['last_status'] . "\n";
    }
}
```

## Lock Files

### Purpose

Lock files prevent concurrent execution of the same task:

```
site/submissions/scheduler/locks/my_task.lock
```

### Lock Timeout

- Locks older than 5 minutes are considered stale
- Stale locks are automatically removed
- Prevents deadlock if task crashes without releasing lock

### Manual Lock Removal

If a task is stuck (shouldn't happen, but...):

```bash
rm site/submissions/scheduler/locks/my_task.lock
```

## Manual Triggering

Tasks can be manually triggered regardless of schedule:

```php
// From within a component
$scheduler = self::$app->scheduler;
$scheduler->triggerTask('my_task_id');
```

Or via API endpoint (admin only):

```php
if ($requestPath === '/api/scheduler/trigger' && $requestMethod === 'POST') {
    if (!$authService->isAdmin()) {
        http_response_code(401);
        return;
    }

    $payload = $this->readJsonPayload();
    $taskId = $payload['task_id'] ?? '';

    $success = $this->scheduler->triggerTask($taskId);
    echo json_encode(['success' => $success]);
    return;
}
```

## Best Practices

### Task Duration

Keep tasks short (< 5 minutes):
- Long tasks block the request
- Risk of lock timeout
- User experiences delay

For long operations:
- Break into smaller chunks
- Use external queue system
- Run via actual cron if available

### Error Handling

Always wrap task logic in try-catch:

```php
$scheduler->registerTask('my_task', $schedule, function() {
    try {
        // Task logic
        self::performOperation();
    } catch (\Exception $e) {
        // Log error
        self::log("Task failed: " . $e->getMessage(), 'error');
        // Don't throw - let scheduler handle it
    }
});
```

### Configuration

Make schedules configurable:

```ini
[mycomponent]
schedule = "daily"
schedule_time = "03:00"
```

This allows users to adjust timing without code changes.

### Testing

Test task execution manually:

```php
// In your component
public static function testTask(): void
{
    // Call your task logic directly
    self::performCleanup();
}
```

Then trigger via admin panel or API.

## Troubleshooting

### Task Not Running

1. **Check Registration**:
   ```php
   $tasks = $scheduler->getTasks();
   print_r($tasks);
   ```

2. **Verify Schedule**:
   - Is schedule type correct?
   - Is time in 24-hour format?
   - Is day value valid?

3. **Check State File**:
   ```bash
   cat site/submissions/scheduler/state/my_task.json
   ```

4. **Look for Locks**:
   ```bash
   ls -la site/submissions/scheduler/locks/
   ```

### Task Running Too Often

- Check schedule configuration
- Verify state file is being written
- Look for duplicate task registrations

### Task Running Too Rarely

- Ensure site is receiving traffic
- Scheduler only runs on page loads
- Consider actual cron for critical tasks

### Stale Locks

- Locks auto-expire after 5 minutes
- If task consistently times out, investigate task logic
- Consider breaking long operations into chunks

## Architecture

### Design Philosophy

The scheduler follows Flint principles:

- **Modular**: Components register tasks via hooks
- **File-Based**: No database required
- **Lightweight**: Minimal overhead on each request
- **Self-Contained**: No external dependencies

### Why Not Real Cron?

Real cron is better for:
- Critical operations that must run precisely on time
- Heavy operations that shouldn't block requests
- High-frequency tasks (every minute)

Pseudo-cron (this system) is better for:
- Shared hosting without cron access
- Simple scheduled tasks
- No server configuration required
- Integrated with component system

### Performance Characteristics

- **Memory**: ~5KB per registered task
- **Disk I/O**: 1 read + 1 write per task execution
- **CPU**: Negligible for typical task counts (< 10 tasks)
- **Scaling**: Suitable for < 20 registered tasks

For high-frequency or CPU-intensive tasks, use real cron.

## Security

### Task Isolation

- Tasks run in same process as web request
- No privilege escalation
- Same permissions as web server

### State File Protection

- Stored in `site/submissions/scheduler/`
- `.htaccess` denies web access
- Only accessible via filesystem

### Lock File Security

- Simple file existence check
- No sensitive data stored
- Auto-cleanup prevents accumulation

## API Reference

### Scheduler Class

Located in `app/core/Scheduler.php`.

#### Constructor

```php
public function __construct(App $app)
```

#### registerTask()

```php
public function registerTask(string $taskId, array $schedule, callable $callback): void
```

Register a scheduled task.

**Parameters:**
- `$taskId`: Unique identifier for the task
- `$schedule`: Schedule configuration array
- `$callback`: Function to execute

#### run()

```php
public function run(): void
```

Check and execute due tasks. Called automatically on each request.

#### isTaskDue()

```php
private function isTaskDue(string $taskId, array $schedule): bool
```

Check if a task should run based on schedule and last run time.

#### executeTask()

```php
private function executeTask(string $taskId, callable $callback): void
```

Execute a task with lock protection and state tracking.

#### getTaskState()

```php
public function getTaskState(string $taskId): ?array
```

Get execution state for a task.

#### getTasks()

```php
public function getTasks(): array
```

Get all registered tasks and their states.

#### triggerTask()

```php
public function triggerTask(string $taskId): bool
```

Manually trigger a task regardless of schedule.

## Examples

### Simple Cleanup Task

```php
$scheduler->registerTask('cleanup_temp', [
    'type' => 'daily',
    'time' => '04:00'
], function() use ($app) {
    $tempDir = $app->root . '/site/temp';
    $cutoff = time() - (7 * 86400); // 7 days ago

    foreach (glob($tempDir . '/*') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
});
```

### API Health Check

```php
$scheduler->registerTask('api_health_check', [
    'type' => 'interval',
    'seconds' => 300  // Every 5 minutes
], function() {
    $response = @file_get_contents('https://api.example.com/health');
    if ($response === false) {
        error_log("API health check failed");
    }
});
```

### Report Generation

```php
$scheduler->registerTask('weekly_report', [
    'type' => 'weekly',
    'day' => 1,  // Monday
    'time' => '09:00'
], function() use ($app) {
    $report = generateWeeklyReport();
    $adminEmail = $app->config['mail']['admin_email'] ?? '';

    if ($adminEmail) {
        mail($adminEmail, "Weekly Report", $report);
    }
});
```

## Future Enhancements

Potential improvements for future versions:

- **Task Queueing**: Queue tasks for background execution
- **Parallel Execution**: Run multiple tasks concurrently
- **Task Dependencies**: Chain tasks with dependencies
- **Retry Logic**: Auto-retry failed tasks
- **Notifications**: Alert on task failures
- **Web UI**: Admin panel for task management
- **Task History**: Log of past executions
- **Performance Metrics**: Track task execution times

## License

Part of Flint. Same license as core system.
