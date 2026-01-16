# Flint Testing Guide

## Overview

Flint uses PHPUnit for comprehensive testing with a focus on security and reliability.

## Test Structure

```
tests/
├── Security/          # Security-focused tests (XSS, injection, traversal)
│   ├── ZipSlipTest.php
│   └── PathTraversalTest.php
├── Unit/              # Unit tests for individual methods
│   └── AppTest.php
└── Integration/       # End-to-end integration tests
```

## Running Tests

### Prerequisites

```bash
# Install dependencies (dev only, not needed for runtime)
composer install

# Or install globally
composer global require phpunit/phpunit
```

### Run All Tests

```bash
composer test
# or
./vendor/bin/phpunit
```

### Run Full Check Suite

```bash
bun run test
```

Runs PHP lint (phpcs), PHPStan, PHPUnit, and JS lint in one pass.

### Auto-Fix Lint Issues

```bash
bun run test:fix
```

Runs phpcbf and eslint --fix to clean up code style issues.

### Run Specific Test Suites

```bash
# Security tests only (CRITICAL)
composer test:security

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration
```

### Run Specific Test Files

```bash
./vendor/bin/phpunit tests/Security/ZipSlipTest.php
```

### Generate Coverage Report

```bash
composer test:coverage
# Opens coverage/index.html
```

## Writing Tests

### Test Naming Conventions

- Test class: `{ClassName}Test.php`
- Test method: `test{MethodName}{Scenario}()`
- Use descriptive names: `testBlocksPathTraversalInZipEntry()`

### Security Test Example

```php
<?php
namespace Tests\Security;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    public function testBlocksMaliciousInput(): void
    {
        $input = '../../../etc/passwd';
        $this->assertStringContainsString('..', $input);
        // Your security check here
    }
}
```

### Unit Test Example

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Flint\App;

class AppTest extends TestCase
{
    public function testSanitizeFilename(): void
    {
        $app = new App('/path');
        $reflection = new \ReflectionClass($app);
        $method = $reflection->getMethod('sanitizeFilename');
        $method->setAccessible(true);

        $result = $method->invoke($app, 'My File!@#.php');
        $this->assertEquals('my-file', $result);
    }
}
```

## Critical Tests

### Security Tests (Must Pass)

1. **ZIP Slip Prevention** - `tests/Security/ZipSlipTest.php`
   - Blocks `../` in ZIP entries
   - Blocks absolute paths
   - Validates extraction directory

2. **Path Traversal Prevention** - `tests/Security/PathTraversalTest.php`
   - Blocks `..` in URLs
   - Validates realpath() checks
   - Blocks symlink exploitation

3. **Upload Validation**
   - MIME type validation
   - File size limits
   - Filename sanitization

### Unit Tests (Must Pass)

1. **sanitizeFilename()**
   - Removes special characters
   - Converts to lowercase
   - Handles empty strings

2. **getMimeType()**
   - Returns correct MIME types
   - Handles unknown extensions

3. **recursiveRemoveDirectory()**
   - Removes nested directories
   - Handles symlinks safely

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: composer test
      - run: composer analyse
```

## Coverage Goals

- **Security tests**: 100% coverage (CRITICAL paths)
- **Unit tests**: 90%+ coverage
- **Integration tests**: 80%+ coverage

## Test Data

Test fixtures are created dynamically in `sys_get_temp_dir()` to avoid polluting the codebase.

## Performance Testing

For performance testing:

```bash
# Use --repeat flag
./vendor/bin/phpunit --repeat=100 tests/Unit/AppTest.php
```

## Static Analysis

```bash
# Run PHPStan
composer analyse

# Check code style
./vendor/bin/phpcs core/ --standard=PSR12
```

## Best Practices

1. **Isolate tests** - Each test should be independent
2. **Clean up** - Always clean up test data in `tearDown()`
3. **Test edge cases** - Empty strings, null, very large inputs
4. **Test security** - Always test with malicious inputs
5. **Fast tests** - Unit tests should run in <0.1s each

## Common Issues

### "Class not found"
```bash
composer dump-autoload
```

### "Permission denied"
```bash
chmod +x vendor/bin/phpunit
```

### Tests timeout
```bash
# Increase timeout in phpunit.xml
<phpunit defaultTimeLimit="10">
```

## Contributing Tests

When adding new features:

1. Write security tests FIRST
2. Write unit tests for new methods
3. Update integration tests
4. Ensure all tests pass before PR

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)
