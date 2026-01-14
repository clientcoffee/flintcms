# Event Submissions Directory

This directory serves as the unified event log for all system events.

## Philosophy

All events that happen on the site are captured here:
- User interactions (form submissions)
- Security events (attacks, blocks)
- Authentication events (logins, logouts)
- System events (errors, warnings)

This creates a single source of truth for auditing, debugging, and analytics.

## Directory Structure

```
content/submissions/
├── .htaccess              # Deny all web access
├── README.md              # This file
├── forms/                 # Form submission events
│   └── form-{timestamp}-{random}.json
├── defense/               # Security/defense events
│   ├── ip-{ip}.json       # IP offense tracking
│   ├── requests-{ip}.json # Request patterns
│   └── cache-{ip}.json    # Rate limit cache
├── backups/               # Backup metadata
│   └── backup-{YmdHis}-{random}.json
├── scheduler/             # Scheduler state and locks
│   ├── state/             # Task execution state
│   │   └── {task_id}.json
│   └── locks/             # Task execution locks
│       └── {task_id}.lock
└── auth/                  # Authentication events (future)
    └── login-{timestamp}-{random}.json
```

## Security

The `.htaccess` file **denies all web access** to ensure event data can only be accessed via:
- Admin panel (authenticated)
- Server filesystem (for backups)
- Never via direct URL

## Event Types

### Forms (forms/)
User-submitted form data. Viewable in Admin → Submissions tab.

### Defense (defense/)
Security events from Defense component. IP tracking, offense patterns, rate limiting.

### Backups (backups/)
Backup metadata including download tokens, creation times, expiration dates. The actual backup tarballs are stored in `content/backups/` but metadata is here for unified event tracking.

### Scheduler (scheduler/)
Cron-like task scheduler state and locks. Two subdirectories:
- **state/**: JSON files tracking last run time, status, errors for each scheduled task
- **locks/**: Lock files preventing concurrent execution of same task

### Auth (auth/) - Future
Login attempts, session management, authentication events.

## Backup & Privacy

- Always include in encrypted backups (contains PII)
- Implement retention policy (auto-delete old events)
- Support GDPR rights (access, erasure, portability)
- Consider IP anonymization after 30 days

See full documentation in `/docs` for detailed event schemas and management.
