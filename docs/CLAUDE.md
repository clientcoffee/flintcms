# Flint Developer Guide

**Version**: 1.0.0
**Last Updated**: 2025-01-04

This document serves as the comprehensive developer guide for Flint. Read this before contributing to the project.

Public CMS documentation lives in `public-docs/README.md`.

---

## Table of Contents

1. [Project Philosophy](#project-philosophy)
2. [Architecture Overview](#architecture-overview)
3. [Directory Structure](#directory-structure)
4. [Getting Started](#getting-started)
5. [Core Systems](#core-systems)
6. [Component Development](#component-development)
7. [Hook System](#hook-system)
8. [Scheduler System](#scheduler-system)
9. [Security Guidelines](#security-guidelines)
10. [Code Standards](#code-standards)
11. [Testing](#testing)
12. [Deployment](#deployment)
13. [Common Patterns](#common-patterns)

---

## What Flint Stands For

### Our Manifesto

Flint is **opinionated**. We make choices so you don't have to. We believe in:

**Quality Over Speed**:
- Code that passes linting is better than code that ships fast
- Standards prevent bugs, save time in the long run
- **NO exceptions** for "quick fixes"

**Security is Non-Negotiable**:
- Assume all input is hostile
- Validate everything, escape everything
- Defense in depth: multiple security layers
- One vulnerability rejected = thousands of attacks prevented

**Simplicity is Sophistication**:
- No frameworks, no ORMs, no build pipelines
- Pure PHP + vanilla JS + flat files
- If you can't explain it simply, redesign it
- **YAGNI** (You Aren't Gonna Need It) - build what's needed, not what might be

**Standards are Freedom**:
- PSR-12 for PHP, StandardJS for JavaScript
- Pre-commit hooks prevent bad code from entering repo
- Consistency means any developer can contribute immediately
- Tools enforce standards, humans create

**Code is Communication**:
- Write for humans first, computers second
- Clear names > clever tricks
- Comments explain "why", code explains "what"
- Delete code you don't need, git remembers

**Modular is Maintainable**:
- Core stays small and stable
- Components extend via hooks, never modify core
- Drop-in components = zero coupling
- Easy to understand, easy to replace

### What We Reject

**Trends**:
- We don't chase the latest framework
- We don't rewrite working code for "modern" alternatives
- Boring technology wins

**Complexity**:
- No micro-optimizations that harm readability
- No "clever" code that needs explanation
- No abstractions for one-time use

**Compromise**:
- No "temporary" hacks
- No "just this once" standard violations
- No "good enough" security

**Technical Debt**:
- Fix it now, not later
- Refactor when you see duplication
- Delete dead code immediately

---

## Project Philosophy

### Core Principles

Flint is built on these foundational principles:

1. **Zero Database**: Everything is file-based (markdown, JSON, INI)
2. **Modular Architecture**: Components extend functionality via hooks, not core modifications
3. **Security First**: Defense in depth, assume hostile input, validate everything
4. **DRY Code**: Eliminate duplication through inheritance and shared utilities
5. **Zero Configuration**: Sensible defaults, works out of the box
6. **Flat Files**: No build step, no compilation, pure PHP
7. **Simplicity**: Prefer simple solutions over complex abstractions
8. **Standards Enforced**: All code must pass phpcs (PSR-12) and ESLint
9. **Quality Gates**: Pre-commit hooks, CI/CD, code review - no shortcuts

### Design Goals

- **Portable**: Works on any PHP hosting (shared, VPS, dedicated)
- **Fast**: Minimal overhead, no ORM, direct file access
- **Hackable**: Easy to understand, modify, extend
- **Secure**: Actively hostile to attacks, Defense component blocks threats
- **Maintainable**: Clear separation of concerns, documented patterns

---

## Architecture Overview

### Request Lifecycle

```
1. index.php
   ↓
2. App::__construct()
   - Load site/config.php (with legacy fallbacks)
   - Enforce security measures
   - Initialize Scheduler
   - Initialize HookManager
   - Load enabled components
   ↓
3. App::run()
   - Validate request path
   - Trigger 'request_start' hook (Defense checks)
   - Run Scheduler (check for due tasks)
   - Serve static files (CSS, JS, images)
   - Match custom routes (hook: 'custom_routes')
   - Route to admin, login, or API
   - Resolve content file (page/block)
   - Render through theme
   ↓
4. Theme::render()
   - Parse markdown with Parser
   - Process {{components}}
   - Apply theme template
   - Output HTML
```

### Core Classes

**Location**: `app/core/`

- **App.php** - Main application, routing, request handling
- **Parser.php** - Markdown parsing, component resolution
- **Auth.php** - Authentication, session management
- **HookManager.php** - Event system, component hooks
- **Scheduler.php** - Pseudo-cron task scheduling
- **BaseComponent.php** - Base class for lifecycle components
- **RenderComponent.php** - Base class for stateless render components
- **Setup.php** - First-time installation wizard

### Component Types

**Lifecycle Components** (extend `BaseComponent`):
- Have state and lifecycle hooks
- Register event handlers
- Examples: Defense, Backups
- Location: `site/components/{Name}/{Name}.php`

**Render Components** (extend `RenderComponent`):
- Stateless, only render output
- Called during markdown parsing
- Examples: Nav, Block, Form, Hero
- Location: `site/components/{Name}/{Name}.php` or themes

**Theme Components** (extend `RenderComponent`):
- Component overrides specific to a theme
- Namespace: `\Modules\`
- Location: `site/themes/{theme}/{Component}.php`

---

## Directory Structure

```
flint/
├── app/                        # Core framework (updated with releases)
│   ├── core/                   # Core PHP services (App, Parser, HookManager, etc.)
│   ├── assets/                 # Admin panel JS/CSS
│   ├── storage/                # Logs, cache, backups
│   ├── views/                  # Admin + error templates
│   ├── index.php               # Runtime entry point
│   └── index-dist.php          # Build-target bootstrap (copied to dist/)
├── site/                       # Drop-in site bundle (never touched by updates)
│   ├── pages/
│   ├── blocks/
│   ├── components/
│   ├── themes/
│   ├── uploads/
│   ├── submissions/
│   ├── config.example.php      # Copy to site/config.php during setup
│   └── config.php              # Generated config (gitignored)
├── dist/                       # Generated distribution (build output)
├── public-docs/                # Public-facing CMS documentation
├── docs/                       # Maintainer docs
├── scripts/
│   ├── build.sh
│   └── migrate-content-to-site.sh
├── tests/
├── CHANGELOG.md
├── README.md
```

---

## Getting Started

### Prerequisites (REQUIRED)

**Minimum versions** (older versions not supported):

- **PHP 8.2** or higher (8.1 minimum, 8.2+ recommended)
- **Composer 2.5** or higher
- **Node.js 18** or higher (for ESLint)
- **Bun 1.1** or higher
- Apache with mod_rewrite OR Nginx with rewrite rules
- `tar` command (for backups)
- `git` for version control
- Write permissions on `site/` and `app/`

**Development tools** (mandatory for contributors):

```bash
# Verify versions
php -v        # Should be >= 8.2
composer -V   # Should be >= 2.5
node -v       # Should be >= 18
bun -v        # Should be >= 1.1
git --version # Should be >= 2.30

# Install required tools
composer require --dev squizlabs/php_codesniffer
bun add -d eslint eslint-config-standard
bun add -d husky
```

### Local Development Setup

1. **Clone the repository**:
   ```bash
   git clone <repo-url> flint
   cd flint
   ```

2. **Set permissions**:
   ```bash
   chmod -R 755 site/
   chmod -R 755 app/
   ```

3. **Start local server**:
   ```bash
   php -S localhost:8000
   ```

4. **Run first-time setup**:
   - Visit `http://localhost:8000`
   - Complete setup wizard
   - Set admin password
   - Configure site settings

5. **Enable components** (optional):
   Edit `site/config.php`:
   ```php
   return [
       'components' => [
           'Defense' => true,
           'Backups' => false,
       ],
   ];
   ```

### Migrating from legacy bundles

Run the migration helper when moving from a release that still stored pages, blocks, or themes inside a monolithic `content/` directory:

```bash
./scripts/migrate-content-to-site.sh /path/to/legacy/content
```

The command syncs the former `pages`, `blocks`, `components`, `themes`, `uploads`, and `submissions` folders into the modern `site/` hierarchy.

### Configuration

**Main config**: `site/config.php`

```php
return [
    'site' => [
        'name' => 'My Site',
        'tagline' => 'Welcome',
        'theme' => 'motion',
    ],
    'mail' => [
        'admin_email' => 'admin@example.com',
    ],
    'components' => [
        'Defense' => true,
        'Backups' => false,
    ],
];
```

**Component config**: `site/components/{Name}/config.php`

Each component has its own config file with component-specific settings.

---

## Core Systems

### 1. Router (App.php)

The router handles all incoming requests and dispatches to appropriate handlers.

**Route Priority**:
1. Static files (`/assets/`, `/themes/`, `/components/`, `/site/uploads/`)
2. Admin routes (`/admin`, `/login`)
3. API routes (`/api/*`)
4. Custom routes (hook: `custom_routes`)
5. Content resolution (`/about` → `site/pages/about.md`)

**Adding Custom Routes**:

Components register routes via `custom_routes` hook:

```php
protected static function registerHooks(): void
{
    self::registerHook('custom_routes', [self::class, 'handleRoutes']);
}

public static function handleRoutes(array $context): bool
{
    $path = $context['path'] ?? '';
    $method = $context['method'] ?? 'GET';

    if ($path === '/my-custom-route') {
        echo "Custom route response";
        return true; // Route handled
    }

    return false; // Route not handled
}
```

### 2. Parser (Parser.php)

The parser converts markdown to HTML and processes embedded components.

**Component Syntax**:

```markdown
<ComponentName prop="value" other="data">
Content here
</ComponentName>
```

Component tags are HTML-style and must use a closing tag. The parser matches
capitalized tag names like `<Hero>` and `<Alert>`. Template placeholders like
`{{magic_link}}` are reserved for email/content templates, not components.

**Component Resolution Order**:
1. Theme components: `site/themes/{theme}/{Component}.php` (namespace: `\Modules\`)
2. Site components: `site/components/{Name}/{Name}.php` (namespace: `\Components\`)
3. Core components: `app/core/components/{Name}.php` (namespace: `\Components\`)

**Example**:

```markdown
<Hero title="Welcome" subtitle="To our site" cta="Get Started" url="/signup">
</Hero>

<Form name="contact" success="Thanks for reaching out!">
name:text:Your Name:true:Enter your name
email:email:Email:true:your@email.com
message:textarea:Message:true::5
</Form>
```

### 3. Authentication (Auth.php)

Simple password-based authentication for admin access.

**Usage**:

```php
$auth = new Auth($app);

// Check if logged in
if ($auth->isAdmin()) {
    // Admin actions
}

// Login
if ($auth->login($password)) {
    // Success
}

// Logout
$auth->logout();
```

**Password Storage**: Bcrypt hash in `site/config.php`

### 4. Hook System (HookManager.php)

Event-driven system for extending functionality without core modifications.

**Available Hooks**:

| Hook | When Fired | Context Parameters | Use Case |
|------|------------|-------------------|----------|
| `request_start` | Start of each request | path, method, ip | Security checks, rate limiting |
| `custom_routes` | After built-in routes | path, method, app | Register custom routes |
| `custom_api_endpoints` | In API handler | path, method, app, auth | Register API endpoints |
| `register_scheduled_tasks` | On app init | scheduler | Register scheduled tasks |
| `form_validate` | Form submission | token, form_type | Form validation |
| `admin_panel_load` | Admin panel loads | N/A | Add admin UI elements |
| `theme_styles` | While rendering `<head>` | N/A | Append inline styles or `<link>` tags after component assets |
| `theme_scripts` | Right before `</body>` | N/A | Insert supplemental footer scripts (widgets, analytics, etc.) |

**Registering Hooks**:

```php
protected static function registerHooks(): void
{
    self::registerHook('request_start', [self::class, 'onRequest']);
}

public static function onRequest(array $context): void
{
    $ip = $context['ip'] ?? '';
    // Handle request
}
```

**Triggering Hooks**:

```php
$results = HookManager::trigger('hook_name', [
    'param1' => 'value1',
    'param2' => 'value2'
]);
```

### 5. Scheduler (Scheduler.php)

Pseudo-cron system for automated tasks. See `app/core/SCHEDULER.md` for full documentation.

**Quick Example**:

```php
protected static function registerHooks(): void
{
    self::registerHook('register_scheduled_tasks', [self::class, 'registerTasks']);
}

public static function registerTasks(array $context): void
{
    $scheduler = $context['scheduler'] ?? null;
    if (!$scheduler) return;

    $scheduler->registerTask('cleanup_daily', [
        'type' => 'daily',
        'time' => '03:00'
    ], function() {
        self::performCleanup();
    });
}
```

---

## Component Development

### Creating a Render Component

**1. Create component file**:

`site/components/Alert/Alert.php`

```php
<?php

namespace Components;

use Flint\RenderComponent;

class Alert extends RenderComponent
{
    public static function render(array $props, string $content): string
    {
        // Get props with defaults
        $type = self::prop($props, 'type', 'info');
        $title = self::prop($props, 'title');
        $message = self::contentOrProp($content, $props, 'message');

        // Escape output
        $title = self::escape($title);
        $message = self::escape($message);

        // Determine color scheme
        $colors = [
            'info' => 'bg-blue-100 border-blue-500 text-blue-900',
            'success' => 'bg-green-100 border-green-500 text-green-900',
            'warning' => 'bg-yellow-100 border-yellow-500 text-yellow-900',
            'error' => 'bg-red-100 border-red-500 text-red-900',
        ];
        $colorClass = $colors[$type] ?? $colors['info'];

        // Render markup with output buffering for readability
        ob_start();
        ?>
        <div class="border-l-4 p-4 <?= $colorClass ?>" role="alert">
            <?php if ($title !== '') : ?>
                <p class="font-bold"><?= $title ?></p>
            <?php endif; ?>
            <p><?= $message ?></p>
        </div>
        <?php

        return trim((string)ob_get_clean());
    }
}
```

### Component Rendering Rules (REQUIRED)

- **No HTML/JS/CSS string concatenation** in components. It is hard to read and easy to break.
- **Keep logic and presentation separate**: compute values first, then render markup.
- **Use output buffering or templates** to render HTML (see the example above).

**2. Use in markdown**:

```markdown
<Alert type="success" title="Success!">
Your changes have been saved.
</Alert>

<Alert type="error" message="Something went wrong.">
</Alert>
```

### Creating a Lifecycle Component

**1. Create component structure**:

```
site/components/EmailNotifier/
├── EmailNotifier.php
├── config.php
└── README.md
```

**2. Component class**:

`site/components/EmailNotifier/EmailNotifier.php`

```php
<?php

namespace Components\EmailNotifier;

use Flint\BaseComponent;
use Flint\HookManager;

class EmailNotifier extends BaseComponent
{
    private static string $logDir = '';

    protected static function onInit(): void
    {
        self::$logDir = self::$app->root . '/site/submissions/email-logs';
        self::ensureStorageDir(self::$logDir);
    }

    protected static function registerHooks(): void
    {
        self::registerHook('form_submit', [self::class, 'onFormSubmit']);
    }

    public static function onFormSubmit(array $context): void
    {
        $formData = $context['data'] ?? [];
        $adminEmail = self::$app->config['mail']['admin_email'] ?? '';

        if (!$adminEmail) {
            self::log("No admin email configured", 'error');
            return;
        }

        $subject = "New form submission";
        $body = self::formatFormData($formData);

        $sent = mail($adminEmail, $subject, $body);

        if ($sent) {
            self::log("Email sent to {$adminEmail}");
        } else {
            self::log("Failed to send email", 'error');
        }
    }

    private static function formatFormData(array $data): string
    {
        $body = "New form submission:\n\n";
        foreach ($data as $key => $value) {
            $body .= ucfirst($key) . ": " . $value . "\n";
        }
        return $body;
    }
}
```

**3. Configuration**:

`site/components/EmailNotifier/config.php`

```php
return [
    'component' => [
        'name' => 'Email Notifier',
        'version' => '1.0.0',
        'author' => 'Your Name',
        'description' => 'Sends email notifications on form submissions',
        'enabled' => false,
        'priority' => 100,
    ],
];
```

**4. Enable component**:

In `site/config.php`:

```php
return [
    'components' => [
        'EmailNotifier' => true,
    ],
];
```

### BaseComponent Helpers

When extending `BaseComponent`, you have access to:

```php
// Storage
protected static function ensureStorageDir(string $path, int $permissions = 0755): bool;

// JSON operations
protected static function writeJsonFile(string $path, mixed $data, int $flags = JSON_PRETTY_PRINT): bool;
protected static function readJsonFile(string $path, bool $associative = true): mixed;

// Networking
protected static function getClientIp(): string; // Supports Cloudflare, X-Forwarded-For

// Logging
protected static function log(string $message, string $level = 'info'): void;

// Config access
protected static function getConfig(string $key, mixed $default = null): mixed;
```

### RenderComponent Helpers

When extending `RenderComponent`, you have access to:

```php
// Property access
protected static function prop(array $props, string $key, mixed $default = ''): mixed;
protected static function propBool(array $props, string $key): bool;
protected static function contentOrProp(string $content, array $props, string $propKey): string;

// HTML utilities
protected static function escape(string $text): string;
protected static function buildAttributes(array $attributes): string;

// Parsing utilities
protected static function parseList(string $text): array;
protected static function parseKeyValue(string $text, string $delimiter = '|'): array;
protected static function parseMarkdownLink(string $text): ?array;

// App access
protected static function getApp(): ?App;
```

---

## Hook System

### Hook Lifecycle

```
1. App boots
   ↓
2. HookManager::init()
   - Scans site/components/
   - Loads enabled components
   - Calls Component::onInit()
   - Calls Component::registerHooks()
   ↓
3. Components register callbacks
   - self::registerHook('event', [self::class, 'method'])
   ↓
4. Application triggers hooks
   - HookManager::trigger('event', $context)
   ↓
5. Callbacks execute
   - Can modify context
   - Can return values
   - Can halt execution (return true)
```

### Creating Custom Hooks

**1. Trigger in core code**:

```php
// In App.php or wherever needed
$results = HookManager::trigger('before_page_render', [
    'path' => $contentPath,
    'page' => $pageData
]);

// Check if any hook halted execution
if (in_array(true, $results, true)) {
    return;
}
```

**2. Components listen**:

```php
protected static function registerHooks(): void
{
    self::registerHook('before_page_render', [self::class, 'modifyPage']);
}

public static function modifyPage(array $context): ?bool
{
    $page = $context['page'] ?? [];

    // Modify page data
    // $page['content'] = preg_replace(...);

    // Return true to halt execution, false/null to continue
    return null;
}
```

### Hook Best Practices

1. **Don't Block**: Keep hook callbacks fast (< 100ms)
2. **Return Properly**: Return `true` to halt, `false`/`null` to continue
3. **Validate Context**: Always check context parameters exist
4. **Handle Errors**: Wrap logic in try-catch, don't crash the app
5. **Document Hooks**: If adding new hooks, document in this file

---

## Scheduler System

See `app/core/SCHEDULER.md` for complete documentation.

### Quick Reference

**Schedule Types**:

- `manual` - Only runs when manually triggered
- `hourly` - Every hour
- `daily` - Once per day at specific time
- `weekly` - Once per week on specific day
- `monthly` - Once per month on specific day
- `interval` - Every N seconds

**Example**:

```php
public static function registerTasks(array $context): void
{
    $scheduler = $context['scheduler'] ?? null;
    if (!$scheduler) return;

    // Daily backup at 3 AM
    $scheduler->registerTask('backup_daily', [
        'type' => 'daily',
        'time' => '03:00'
    ], function() {
        self::createBackup();
    });

    // Cleanup every 6 hours
    $scheduler->registerTask('cleanup_cache', [
        'type' => 'interval',
        'seconds' => 21600
    ], function() {
        self::cleanupCache();
    });
}
```

**Checking Task State**:

```php
$state = $app->scheduler->getTaskState('backup_daily');
if ($state) {
    $lastRun = $state['last_run'] ?? 0;
    $status = $state['last_status'] ?? 'never';
    $error = $state['last_error'] ?? null;
}
```

---

## Security Guidelines

### Input Validation

**ALWAYS validate and sanitize**:

```php
// Path validation - prevent directory traversal
if (strpos($path, '..') !== false) {
    http_response_code(400);
    return;
}

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['error' => 'Invalid email'];
}

// File upload validation
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    return ['error' => 'Invalid file type'];
}

// HTML output - ALWAYS escape
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

### Security Patterns

**1. Defense in Depth**:

Multiple security layers:
- `.htaccess` denies direct access
- File permissions prevent execution
- Input validation rejects bad data
- Output escaping prevents XSS

**2. Deny by Default**:

```apache
# In site/uploads/.htaccess
<FilesMatch "\.(php|php3|php4|php5|phtml)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

**3. Least Privilege**:

```bash
# Directories: read + execute
chmod 755 site/

# Files: read only
chmod 644 site/pages/*.md

# Sensitive files: owner only
chmod 600 site/config.php
```

**4. Rate Limiting**:

Use Defense component for automatic rate limiting:

```php
// Defense blocks IPs after offense threshold
// See site/components/Defense/config.php
```

**5. Token Authentication**:

For secure downloads/actions:

```php
// Generate token
$token = bin2hex(random_bytes(32)); // 64-char hex

// Validate token
if ($metadata['token'] === $token && time() < $metadata['expires']) {
    // Valid
}
```

### Common Vulnerabilities to Avoid

**1. SQL Injection**: N/A (no database)

**2. XSS (Cross-Site Scripting)**:

```php
// BAD
echo $_GET['name'];

// GOOD
echo htmlspecialchars($_GET['name'], ENT_QUOTES, 'UTF-8');

// BETTER (use helper)
echo self::escape($_GET['name']);
```

**3. CSRF (Cross-Site Request Forgery)**:

```php
// Forms include CSRF tokens (handled automatically)
// Verify in POST handlers
$token = $_POST['form_token'] ?? '';
// Defense component validates via form_validate hook
```

**4. Path Traversal**:

```php
// BAD
$file = $_GET['file'];
include($file);

// GOOD
if (strpos($file, '..') !== false || $file[0] === '/') {
    http_response_code(400);
    exit;
}
$realPath = realpath($file);
if (strpos($realPath, $allowedDir) !== 0) {
    http_response_code(403);
    exit;
}
```

**5. File Upload Attacks**:

```php
// ALWAYS validate:
// 1. Extension
// 2. MIME type (via finfo)
// 3. File size
// 4. Remove execute permissions
// 5. Store outside webroot if possible

chmod($uploadedFile, 0644); // No execute
```

---

## Code Standards

### Mandatory Tooling

**ALL code MUST pass these checks before commit**:

1. **PHP CodeSniffer** - All PHP files must pass PSR-12
2. **ESLint** - All JavaScript files must pass linting
3. **Pre-commit hooks** - Automatically enforce standards

**No exceptions**. Code that doesn't meet these standards will be rejected.

### PHP Standards (REQUIRED)

**PHP CodeSniffer Configuration**:

All PHP code MUST pass phpcs with PSR-12 standard.

**Install phpcs**:

```bash
composer require --dev squizlabs/php_codesniffer
```

**Run phpcs**:

```bash
# Check all PHP files
./vendor/bin/phpcs --standard=PSR12 app/ site/components/

# Auto-fix what can be fixed
./vendor/bin/phpcbf --standard=PSR12 app/ site/components/
```

**Required Rules (PSR-12)**:

- 4 spaces for indentation (NO TABS)
- Unix line endings (LF, not CRLF)
- Opening braces on same line for methods/functions
- Opening braces on next line for classes
- One class per file
- Namespace declarations at top
- `declare(strict_types=1);` after opening PHP tag
- No trailing whitespace
- Files must end with single newline

**Example**:

```php
<?php

namespace Components\MyComponent;

use Flint\BaseComponent;

class MyComponent extends BaseComponent
{
    private static string $property = '';

    protected static function onInit(): void
    {
        // Initialization logic
    }

    public static function someMethod(string $param): array
    {
        if ($param === '') {
            return ['error' => 'Invalid'];
        }

        return ['success' => true];
    }

    private static function helperMethod(): void
    {
        // Helper logic
    }
}
```

### JavaScript Standards (REQUIRED)

**ESLint Configuration**:

All JavaScript code MUST pass ESLint with StandardJS rules.

### Frontend Asset Rules (REQUIRED)

- **Tailwind is local**: use `app/assets/css/tailwind.min.css` and serve it from `/assets/css/tailwind.min.css`.
- **No Tailwind CDN**: do not include `https://cdn.tailwindcss.com` in any view.
- **Admin UI theme**: admin views must use `app/views/layouts/admin.php` and the internal theme in `app/assets/css/admin.css`.

**Install ESLint**:

```bash
bun add -d eslint eslint-config-standard
```

**Configuration** (`.eslintrc.json`):

```json
{
  "extends": "standard",
  "env": {
    "browser": true,
    "es2021": true
  },
  "rules": {
    "semi": ["error", "always"],
    "indent": ["error", 2],
    "quotes": ["error", "single"],
    "no-console": "warn",
    "no-unused-vars": "error"
  }
}
```

**Run ESLint**:

```bash
# Check all JS files
bun run lint:js

# Auto-fix what can be fixed
bun run lint:js:fix
```

### Unified Checks (REQUIRED)

```bash
# Run PHP lint, PHPStan, PHPUnit, and JS lint together
bun run test

# Auto-fix PHP + JS lint issues
bun run test:fix
```

**Required Rules**:

- 2 spaces for indentation (JavaScript)
- Semicolons required
- Single quotes for strings
- No unused variables
- No console.log in production code (use warn/error only)
- Unix line endings (LF)

### Pre-Commit Hooks (REQUIRED)

**Install Husky**:

```bash
bun add -d husky
bunx husky install
```

**Create pre-commit hook** (`.husky/pre-commit`):

```bash
#!/bin/sh
. "$(dirname "$0")/_/husky.sh"

# Run phpcs on staged PHP files
STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep ".php$")

if [ -n "$STAGED_PHP_FILES" ]; then
  echo "Running phpcs on staged PHP files..."
  ./vendor/bin/phpcs --standard=PSR12 $STAGED_PHP_FILES

  if [ $? -ne 0 ]; then
    echo "❌ phpcs failed. Run phpcbf to auto-fix or fix manually."
    echo "   ./vendor/bin/phpcbf --standard=PSR12 $STAGED_PHP_FILES"
    exit 1
  fi
fi

# Run ESLint on staged JS files
STAGED_JS_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep ".js$")

if [ -n "$STAGED_JS_FILES" ]; then
  echo "Running ESLint on staged JS files..."
  bunx eslint $STAGED_JS_FILES

  if [ $? -ne 0 ]; then
    echo "❌ ESLint failed. Run with --fix to auto-fix or fix manually."
    echo "   bunx eslint --fix $STAGED_JS_FILES"
    exit 1
  fi
fi

echo "✅ All checks passed"
```

**Make executable**:

```bash
chmod +x .husky/pre-commit
```

### Naming Conventions (REQUIRED)

**Classes**: PascalCase (MANDATORY)
```php
class EmailNotifier extends BaseComponent
```

**Methods**: camelCase (MANDATORY)
```php
public static function sendEmail(): void
```

**Properties**: camelCase (MANDATORY)
```php
private static string $storageDir = '';
```

**Constants**: SCREAMING_SNAKE_CASE (MANDATORY)
```php
const MAX_FILE_SIZE = 5242880;
```

**Files**: Match class name exactly (MANDATORY)
```
EmailNotifier.php (contains EmailNotifier class)
```

**JavaScript Functions**: camelCase (MANDATORY)
```javascript
function loadSubmissions() { }
```

**JavaScript Constants**: SCREAMING_SNAKE_CASE (MANDATORY)
```javascript
const MAX_RETRIES = 3;
```

### Documentation

**Class-level docblocks**:

```php
/**
 * Email notification component
 *
 * Sends email alerts for form submissions and system events.
 * Supports multiple recipients and customizable templates.
 */
class EmailNotifier extends BaseComponent
```

**Method-level docblocks**:

```php
/**
 * Send email notification
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject line
 * @param string $body Email body content
 * @return bool True if sent successfully
 */
public static function sendEmail(string $to, string $subject, string $body): bool
```

**Inline comments for complex logic**:

```php
// Calculate offset accounting for timezone differences
$offset = $timestamp - ($timezone * 3600);
```

### Error Handling

**Use try-catch for risky operations**:

```php
try {
    $result = self::performOperation();
    return ['success' => true, 'data' => $result];
} catch (\Exception $e) {
    self::log("Operation failed: " . $e->getMessage(), 'error');
    return ['success' => false, 'error' => $e->getMessage()];
}
```

**Return structured responses**:

```php
// Success
return [
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed'
];

// Error
return [
    'success' => false,
    'error' => 'Error message',
    'code' => 'ERROR_CODE'
];
```

### DRY Principle

**Before (duplicated code)**:

```php
class ComponentA {
    public static function render($props, $content) {
        $title = isset($props['title']) ? $props['title'] : '';
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        // ...
    }
}

class ComponentB {
    public static function render($props, $content) {
        $title = isset($props['title']) ? $props['title'] : '';
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        // ...
    }
}
```

**After (using base class)**:

```php
class ComponentA extends RenderComponent {
    public static function render($props, $content) {
        $title = self::escape(self::prop($props, 'title'));
        // ...
    }
}

class ComponentB extends RenderComponent {
    public static function render($props, $content) {
        $title = self::escape(self::prop($props, 'title'));
        // ...
    }
}
```

---

## Testing

### Manual Testing Checklist

**New Component**:

- [ ] Component loads without errors
- [ ] Config settings work as expected
- [ ] Hooks trigger at correct times
- [ ] Output is properly escaped
- [ ] Error cases handled gracefully
- [ ] Works with component disabled

**Security Testing**:

- [ ] Try path traversal: `../../etc/passwd`
- [ ] Try XSS: `<script>alert('xss')</script>`
- [ ] Try invalid file uploads
- [ ] Try exceeding rate limits
- [ ] Try accessing protected files directly

**Form Testing**:

- [ ] Submit with valid data
- [ ] Submit with missing required fields
- [ ] Submit with invalid email format
- [ ] Submit with XSS attempts
- [ ] Check CSRF token validation
- [ ] Verify rate limiting (60 second cooldown)

### API Testing

**Using curl**:

```bash
# Create backup (admin only)
curl -X POST http://localhost:8000/api/backup/create \
  -H "Cookie: PHPSESSID=your-session-id"

# List backups
curl http://localhost:8000/api/backup/list \
  -H "Cookie: PHPSESSID=your-session-id"

# Submit form
curl -X POST http://localhost:8000/api/form \
  -H "Content-Type: application/json" \
  -d '{
    "form_token": "token-value",
    "form_name": "contact",
    "name": "John Doe",
    "email": "john@example.com",
    "message": "Hello"
  }'
```

### Component Testing Template

```php
// Test in site/pages/test.md

# Component Tests

## Alert Component

<Alert type="info" title="Info">
This is an info alert.
</Alert>

<Alert type="success" title="Success">
This is a success alert.
</Alert>

<Alert type="error" title="Error">
This is an error alert.
</Alert>

## Form Component

<Form name="test" success="Form submitted!">
name:text:Name:true
email:email:Email:true
</Form>
```

---

## Deployment

### Pre-Deployment Checklist

- [ ] Update version in `app/core/Version.php`
- [ ] Test all forms work
- [ ] Test admin login/logout
- [ ] Verify Defense component enabled
- [ ] Check all `.htaccess` files present
- [ ] Verify file permissions correct
- [ ] Test backup creation (if enabled)
- [ ] Review error logs for warnings
- [ ] Clear any test data

### File Permissions

```bash
# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Protect config
chmod 600 site/config.php

# Protect submissions
chmod 750 site/submissions/

# Make index.php executable (if needed)
chmod 644 index.php
```

### .htaccess Protection

Ensure these `.htaccess` files exist:

```bash
app/.htaccess                          # Deny all
site/submissions/.htaccess          # Deny all
site/uploads/.htaccess              # Prevent PHP execution
site/pages/.htaccess                # Block direct access (optional)
site/blocks/.htaccess               # Block direct access (optional)
```

### Environment-Specific Config

**Development** (`site/config.php`):

```php
return [
    'system' => [
        'environment' => 'development',
        'show_errors' => true,
    ],
];
```

**Production** (`site/config.php`):

```php
return [
    'system' => [
        'environment' => 'production',
        'show_errors' => false,
    ],
];
```

### Backup Strategy

**Automated Backups**:

Enable Backups component with daily schedule:

```php
// site/components/Backups/config.php
return [
    'backups' => [
        'schedule' => 'daily',
        'schedule_time' => '03:00',
        'max_backups' => 10,
    ],
];
```

**Manual Backups**:

```bash
# Via admin panel
# Admin → Backups → Create Backup

# Via CLI (if needed)
php -r "require 'index.php'; \Components\Backups\Backups::createBackup();"
```

### Monitoring

**Check logs**:

```bash
# Defense logs
tail -f site/submissions/defense/*.json

# Form submissions
tail -f site/submissions/forms/*.json

# Scheduler state
cat site/submissions/scheduler/state/*.json
```

**Health Check Endpoint** (optional):

Create `site/pages/health.md`:

```markdown
---
title: Health Check
---

System OK
```

Then monitor: `curl http://yoursite.com/health`

---

## Common Patterns

### Pattern 1: Config-Driven Behavior

```php
// Component reads config to determine behavior
$enabled = self::getConfig('mycomponent.feature_enabled', false);

if ($enabled) {
    // Feature logic
}
```

### Pattern 2: Hook-Based Extension

```php
// Core triggers hook
$result = HookManager::trigger('before_save', ['data' => $data]);

// Component modifies data
public static function onBeforeSave(array $context): void
{
    $data = &$context['data'];
    $data['modified'] = true;
}
```

### Pattern 3: Date-Based Storage

```php
// Store files organized by date
$yearMonth = date('Y-m');
$dir = self::$app->root . '/site/uploads/' . $yearMonth;
self::ensureStorageDir($dir);
```

### Pattern 4: Token Authentication

```php
// Generate secure token
$token = bin2hex(random_bytes(32));
$expiry = time() + 86400; // 24 hours

// Store metadata
$metadata = [
    'token' => $token,
    'expires' => $expiry,
    'data' => $data
];

// Validate later
if ($metadata['token'] === $providedToken && time() < $metadata['expires']) {
    // Valid
}
```

### Pattern 5: Lock-Protected Operations

```php
// Acquire lock
$lockFile = $this->lockDir . "/{$taskId}.lock";
if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 300) {
    return; // Already running
}
file_put_contents($lockFile, time());

try {
    // Critical operation
} finally {
    @unlink($lockFile);
}
```

### Pattern 6: Graceful Degradation

```php
// Attempt operation, fall back on failure
try {
    $result = self::primaryMethod();
} catch (\Exception $e) {
    self::log("Primary method failed, using fallback", 'warning');
    $result = self::fallbackMethod();
}
```

### Pattern 7: Event Log Structure

```php
// Store events in submissions/
$eventData = [
    'timestamp' => time(),
    'type' => 'user_action',
    'ip' => self::getClientIp(),
    'data' => $data
];

$filename = 'event-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
$filepath = self::$app->root . '/site/submissions/events/' . $filename;

self::writeJsonFile($filepath, $eventData);
```

---

## Troubleshooting

### Component Not Loading

1. Check `site/config.php` - is it enabled?
2. Check namespace matches directory name
3. Check class name matches filename
4. Look for PHP syntax errors: `php -l ComponentName.php`
5. Check file permissions: `ls -la site/components/`

### Hooks Not Firing

1. Verify hook is registered in `registerHooks()`
2. Check hook name matches exactly (case-sensitive)
3. Add debug logging in hook callback
4. Verify component is enabled and loaded
5. Check hook is triggered in core code

### Scheduler Not Running

1. Ensure site is receiving traffic (scheduler runs on page loads)
2. Check schedule configuration in component config
3. Check for stale locks: `ls site/submissions/scheduler/locks/`
4. Review task state: `cat site/submissions/scheduler/state/task_id.json`
5. Verify component registered task in `register_scheduled_tasks` hook

### Forms Not Submitting

1. Check form endpoint: `/api/form`
2. Verify CSRF token present
3. Check Defense component not blocking (rate limit)
4. Look at browser console for JavaScript errors
5. Check `site/submissions/forms/` for submission files

### Backups Failing

1. Verify `tar` command available: `which tar`
2. Check directory permissions: `ls -la site/uploads/`
3. Check disk space: `df -h`
4. Review backup component logs
5. Try manual backup via admin panel

---

## Contributing

### Pull Request Process (MANDATORY WORKFLOW)

**Feature-Branch Workflow** (strict requirement):
- All changes (including internal work) must go through a feature branch.
- Direct commits to `main` are not allowed.

1. **Fork repository** (if external contributor)
2. **Create feature branch from dev**:
   ```bash
   git checkout dev
   git pull origin dev
   git checkout -b feature/my-feature-name
   ```

3. **Work on your feature**:
   - Make atomic commits (one logical change per commit)
   - Run phpcs and ESLint before each commit
   - Pre-commit hooks will enforce standards

4. **Before creating PR - MERGE DEV**:
   ```bash
   # CRITICAL: Always merge dev before opening PR
   git checkout dev
   git pull origin dev
   git checkout feature/my-feature-name
   git merge dev
   # Resolve conflicts if any
   git push origin feature/my-feature-name
   ```

5. **Test thoroughly**:
   - Run manual test checklist (see Testing section)
   - Verify automated checks pass locally
   - Test in clean environment if possible

6. **Update documentation**:
   - Update CLAUDE.md if adding new patterns/systems
   - Update component README if modifying component
   - Add inline comments for complex logic

7. **Open Pull Request**:
   - Target the `dev` branch
   - Follow `.github/PULL_REQUEST_TEMPLATE.md`
   - Use descriptive title: "Add: User authentication system"
   - Include:
     - What changed
     - Why it changed
     - How to test
     - Any breaking changes
   - Link related issues
   - Add screenshots for UI changes

8. **Code Review**:
   - Address reviewer feedback promptly
   - Push fixes to same branch
   - DO NOT force-push after review starts
   - Mark conversations as resolved when fixed

9. **Merge**:
   - Squash commits if requested
   - Maintainer merges when approved
   - Delete feature branch after merge

**Branch Naming**:
- `feature/description` - New features
- `fix/description` - Bug fixes
- `refactor/description` - Code refactoring
- `docs/description` - Documentation only
- `security/description` - Security fixes

**Examples**:
- `feature/user-authentication`
- `fix/form-validation-bug`
- `refactor/cleanup-parser`
- `docs/update-hook-guide`

### Commit Message Format

```
Type: Short description (50 chars max)

Longer explanation if needed (wrap at 72 chars).
Explain what and why, not how.

- Bullet points okay
- Use present tense: "Add feature" not "Added feature"
```

**Types**: Add, Fix, Update, Remove, Refactor, Docs, Security

### What We DON'T Allow

**Absolute No-Gos** (instant rejection):

1. **Tabs for indentation** - Spaces only (4 for PHP, 2 for JS)
2. **Windows line endings (CRLF)** - Unix (LF) only
3. **Unescaped output** - ALWAYS escape user input in HTML
4. **Direct $_GET/$_POST access without validation** - Validate everything
5. **console.log() in production code** - Use proper logging
6. **Commented-out code** - Delete it, git remembers
7. **TODO comments without issue tracking** - Create GitHub issue or remove
8. **Hardcoded credentials** - Use config files (gitignored)
9. **SQL queries** - This is a flat-file CMS, no database
10. **jQuery or legacy libraries** - Vanilla JS only
11. **eval() or similar** - Security nightmare
12. **Suppressed errors with @** - Handle errors properly
13. **Short PHP tags (<?)** - Use full tags (<?php)
14. **Global variables** - Use proper scoping
15. **Functions with > 50 lines** - Break into smaller functions

### CI/CD Requirements (REQUIRED)

**GitHub Actions Configuration** (`.github/workflows/ci.yml`):

```yaml
name: CI

on:
  pull_request:
    branches: [ dev ]
  push:
    branches: [ dev ]

jobs:
  changes:
    runs-on: ubuntu-latest
    outputs:
      php: ${{ steps.filter.outputs.php }}
      js: ${{ steps.filter.outputs.js }}
    steps:
      - uses: actions/checkout@v4
      - uses: dorny/paths-filter@v3
        id: filter
        with:
          filters: |
            php:
              - 'app/**/*.php'
              - 'site/**/*.php'
              - 'tests/**/*.php'
              - 'composer.json'
              - 'composer.lock'
              - 'phpunit.xml'
              - 'phpcs.xml'
            js:
              - 'app/assets/js/**/*.js'
              - 'package.json'
              - 'bun.lockb'

  lint-php:
    runs-on: ubuntu-latest
    needs: changes
    if: needs.changes.outputs.php == 'true'
    strategy:
      matrix:
        php: ['8.2', '8.3', '8.4']
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}

      - name: Install Composer dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run phpcs
        run: composer lint:php

  js-lint:
    runs-on: ubuntu-latest
    needs: changes
    if: needs.changes.outputs.js == 'true'
    steps:
      - uses: actions/checkout@v4

      - name: Setup Bun
        uses: oven-sh/setup-bun@v1
        with:
          bun-version: '1.1.6'

      - name: Install Bun dependencies
        run: bun install --frozen-lockfile

      - name: Run ESLint
        run: bun run lint:js
```

Other CI jobs (`analyse-php`, `unit-php`, `coverage`) run on PHP changes and use the same PHP matrix.

**All PRs MUST**:
- Target the `dev` branch (CI blocks other base branches)
- Update `CHANGELOG.md` for any code changes (pr-guard enforces this)
- Pass `bun run test` (phpcs, PHPStan, PHPUnit, ESLint)
- Pass security audit when required
- Have no merge conflicts
- Be reviewed by at least one maintainer

### Code Review Criteria (MANDATORY)

**Automated Checks** (must pass):
- [ ] `bun run test` passes (phpcs, PHPStan, PHPUnit, ESLint)
- [ ] Security audit passes when required
- [ ] No merge conflicts

**Manual Review** (reviewer verifies):
- [ ] No security vulnerabilities
- [ ] Proper error handling
- [ ] Input validation present
- [ ] Output properly escaped
- [ ] Documentation updated (CLAUDE.md if needed)
- [ ] No code duplication
- [ ] Component is modular (uses hooks, not core edits)
- [ ] Backwards compatible (if applicable)
- [ ] Tests pass (manual testing checklist completed)
- [ ] No forbidden patterns (see "What We DON'T Allow")

**Review Checklist for Reviewer**:

```markdown
## Code Review

- [ ] Automated checks passed (CI)
- [ ] Security review (no XSS, no path traversal, proper validation)
- [ ] Architecture review (modular, uses hooks, DRY)
- [ ] Code quality (readable, maintainable, follows standards)
- [ ] Documentation (adequate comments, updated CLAUDE.md if needed)
- [ ] Testing (manual tests performed, results documented)

## Verdict

- [ ] **Approve** - Merge ready
- [ ] **Request Changes** - Issues must be fixed
- [ ] **Comment** - Suggestions for improvement
```

---

## Resources

### Internal Documentation

- `app/core/SCHEDULER.md` - Scheduler system documentation
- `site/components/*/README.md` - Component-specific docs
- `site/submissions/README.md` - Event log structure
- Component config files: `site/components/*/config.php`

### External Resources

- [PHP Documentation](https://www.php.net/docs.php)
- [Markdown Guide](https://www.markdownguide.org/)
- [Apache mod_rewrite](https://httpd.apache.org/docs/current/mod/mod_rewrite.html)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

---

## Version History

### 1.0.0 (2025-01-04)

**Core Systems**:
- App router with hook-based extension
- Parser with component resolution
- Authentication system
- Hook system (HookManager)
- Scheduler system (pseudo-cron)
- BaseComponent and RenderComponent base classes

**Components**:
- Defense: IP blocking, rate limiting, attack detection
- Backups: Automated backups with secure downloads
- Form: Complete form handling with email delivery

**Security**:
- .htaccess protection throughout
- Input validation and output escaping
- File upload security
- CSRF protection
- Rate limiting

**Architecture**:
- Modular component system
- Event-driven hooks
- File-based storage (no database)
- Theme support with component cascade

---

## Questions?

For questions, issues, or contributions:

1. Check this document first
2. Review relevant component README files
3. Search existing issues on GitHub
4. Open new issue with detailed description
5. Join discussion in pull requests

---

**Last Updated**: 2025-01-04
**Document Version**: 1.0.0
**Maintained By**: Flint Core Team
