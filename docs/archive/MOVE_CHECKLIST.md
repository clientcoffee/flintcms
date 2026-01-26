# Move Tests Checklist

## Files to Move from `app/` to Parent Directory

### Test Files
- [ ] `tests/Security/ZipSlipTest.php`
- [ ] `tests/Security/PathTraversalTest.php`
- [ ] `tests/Unit/AppTest.php`

### Configuration Files
- [ ] `phpunit.xml`
- [ ] `composer.json`

### Optional (Consider)
- [ ] `TESTING.md` (documents the test suite)

---

## Files to Update After Move

### Test Files (Update `require_once` paths)
- [ ] `tests/Security/ZipSlipTest.php` - Line with `require_once __DIR__ . '/../../core/App.php'`
- [ ] `tests/Security/PathTraversalTest.php` - Line with `require_once __DIR__ . '/../../core/App.php'`
- [ ] `tests/Unit/AppTest.php` - Line with `require_once __DIR__ . '/../../core/App.php'`

**New path should be**: `require_once __DIR__ . '/../app/core/App.php'`

### Configuration Files
- [ ] `phpunit.xml` - Verify `<directory>` paths in testsuites
- [ ] Update to use `app/tests/` if needed

### Documentation
- [ ] `TESTING.md` - Update all command examples and path references
- [ ] `CLAUDE.md` - Update any test location references (if any)

---

## Verification Steps

- [ ] Run `composer install` from parent directory
- [ ] Run `composer test` to execute all tests
- [ ] Verify all 20 tests pass:
  - [ ] 3 tests in ZipSlipTest
  - [ ] 5 tests in PathTraversalTest
  - [ ] 12 tests in AppTest
- [ ] Run `composer test:security` to verify security test suite
- [ ] Check that test output shows correct file paths

---

## Commands to Run After Move

```bash
# From parent directory: /Volumes/data1/Work/temp/flint/
cd /Volumes/data1/Work/temp/flint/

# Install dependencies
composer install

# Run all tests
composer test

# Run security tests only
composer test:security

# Run with verbose output
./vendor/bin/phpunit --verbose

# Generate coverage report
composer test:coverage
```

---

## Success Criteria

✅ All test files moved to parent `tests/` directory
✅ Configuration files moved to parent directory
✅ All `require_once` paths updated correctly
✅ `composer install` runs without errors
✅ `composer test` executes successfully
✅ All 20 tests pass
✅ Security test suite runs independently
✅ Documentation reflects new structure

---

## Rollback Plan (If Needed)

If something goes wrong:
1. Tests are still in `app/tests/` (move operation preserves originals)
2. Original `phpunit.xml` and `composer.json` still in `app/`
3. Can restore by moving files back to `app/`
