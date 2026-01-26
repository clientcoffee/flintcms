# Flint Core Code Audit

## App.php Findings

### DRY Violations
1. **MIME type mapping duplicated** (lines 48-61, 95-107, 130-142)
   - Same array defined 3+ times
   - **Fix**: Extract to private `getMimeType(string $extension): string` method

2. **File serving logic repeated** (lines 42-67, 70-79, 82-114, 117-149)
   - Pattern: check file exists, set MIME, readfile, exit
   - **Fix**: Extract to `serveStaticFile(string $path): void` method

3. **Directory traversal check inconsistent** (line 37 vs line 88, 123)
   - Line 37: `str_contains($requestPath, '..')`
   - Line 88: `strpos($assetPath, '..') !== false`
   - **Fix**: Use consistent method, extract to `containsPathTraversal(string $path): bool`

### Unnecessary else Statements
- None found (good use of early returns)

### Security Risks - CRITICAL

1. **ZIP SLIP ATTACK** (lines 310-350) - CRITICAL
   - `$zip->extractTo($themeDir)` extracts without validating entry paths
   - Malicious ZIP with `../../evil.php` can write outside theme directory
   - **Risk**: CRITICAL - Remote Code Execution
   - **Fix**: Validate each entry path before extraction, ensure no `..`

2. **Path traversal in uploads** (line 72)
   - `$uploadFilePath = $this->root . $requestPath;`
   - No validation that path stays within /site/uploads/
   - **Risk**: HIGH - Directory traversal via `/site/uploads/../../config.ini`
   - **Fix**: Validate resolved path with `realpath()` and ensure it starts with expected directory

3. **SVG XSS** (line 260, 56)
   - Allows `image/svg+xml` uploads and serves with image/* MIME type
   - SVG can contain JavaScript that executes in browser
   - **Risk**: HIGH - Stored XSS
   - **Fix**: Sanitize SVG or serve with `Content-Disposition: attachment`

4. **Email header injection** (lines 1070-1100)
   - Email construction needs audit
   - **Risk**: MEDIUM
   - **Fix**: Validate email addresses, sanitize headers

5. **Config file writing** (lines 473-480)
   - Direct string interpolation into INI file
   - **Risk**: MEDIUM - INI injection if values contain special chars
   - **Fix**: Proper escaping with `addslashes()` or quote values

6. **readJsonPayload() missing** (line 192)
   - Method called but doesn't exist in the code shown
   - Should be readJsonBody() (line 789)
   - **Risk**: LOW - Fatal error
   - **Fix**: Rename to correct method name

### Code Improvements
1. **Magic numbers** - HTTP status codes used directly (403, 404, 500, etc.)
   - **Fix**: Extract to class constants

2. **Long methods** - `run()` method is 200+ lines
   - **Fix**: Extract routing logic to separate methods

3. **Error handling inconsistent** - Mix of exceptions, http_response_code(), echo, exit
   - **Fix**: Standardize error handling

## Unit Tests Needed

### App::__construct()
- [ ] Test throws exception when config.ini missing
- [ ] Test loads config correctly with valid ini file
- [ ] Test handles malformed ini file
- [ ] Test sets readonly properties correctly

### App::run() - Path Traversal
- [ ] Test blocks .. in request path
- [ ] Test allows valid paths
- [ ] Test blocks encoded path traversal (%2e%2e)
- [ ] Test blocks double-encoded traversal
- [ ] Test blocks null byte injection

### App::run() - Static File Serving
- [ ] Test serves JS file with correct MIME type
- [ ] Test serves CSS file with correct MIME type
- [ ] Test serves image files (jpg, png, gif, svg)
- [ ] Test serves font files (woff, woff2, ttf, eot)
- [ ] Test returns 404 for missing static file
- [ ] Test blocks directory listing
- [ ] Test validates file is actually a file (not directory)

### App::run() - Upload File Serving
- [ ] Test serves uploaded files
- [ ] Test MIME type detection
- [ ] Test blocks traversal to /site/uploads/../../
- [ ] Test blocks accessing files outside uploads directory
- [ ] Test handles symlinks properly
- [ ] Test validates file exists and is readable

### App::run() - Theme Assets
- [ ] Test serves theme CSS/JS files
- [ ] Test blocks .. in theme asset path
- [ ] Test validates theme name (alphanumeric, dash, underscore only)
- [ ] Test returns 404 for non-existent theme
- [ ] Test correct MIME types for theme assets

### App::run() - Component Assets
- [ ] Test serves component assets
- [ ] Test blocks path traversal in component path
- [ ] Test validates component name
- [ ] Test correct MIME types

### App::run() - Routing
- [ ] Test routes /login to renderLoginPage()
- [ ] Test routes /admin to renderAdminPage() (requires auth)
- [ ] Test routes /api/* to handleAPI()
- [ ] Test resolves content files for valid paths
- [ ] Test returns 404 for missing content
- [ ] Test handles trailing slashes
- [ ] Test handles case sensitivity

### App::handleAPI() - Authentication
- [ ] Test /api/login with correct password
- [ ] Test /api/login with incorrect password
- [ ] Test /api/login requires POST method
- [ ] Test /api/login validates JSON body
- [ ] Test /api/logout clears session
- [ ] Test /api/status returns correct auth status

### App::handleAPI() - Upload Endpoint
- [ ] Test uploads image file successfully
- [ ] Test uploads PDF successfully
- [ ] Test uploads markdown file successfully
- [ ] Test uploads ZIP (non-theme)
- [ ] Test uploads ZIP with theme.ini as theme
- [ ] Test blocks upload when not admin
- [ ] Test validates file size (5MB for images, 50MB for ZIP)
- [ ] Test validates MIME types
- [ ] Test sanitizes filename
- [ ] Test creates year-month subdirectories
- [ ] Test prevents duplicate filenames (unique suffix)
- [ ] Test blocks upload of executable files
- [ ] Test blocks upload with malicious filenames
- [ ] Test validates ZIP contains theme.ini in root
- [ ] Test theme extraction security (no path traversal in ZIP)

### App::handleAPI() - Settings Endpoint
- [ ] Test requires admin authentication
- [ ] Test GET returns config as flat structure
- [ ] Test POST saves settings correctly
- [ ] Test protects admin.password from modification
- [ ] Test protects system.root from modification
- [ ] Test allows custom settings
- [ ] Test validates section.key format
- [ ] Test handles missing config file
- [ ] Test escapes values correctly in INI output
- [ ] Test preserves protected settings when saving

### App::handleAPI() - Content Endpoint
- [ ] Test requires admin auth
- [ ] Test returns raw markdown for valid path
- [ ] Test validates path (no traversal)
- [ ] Test returns error for non-existent page
- [ ] Test handles pages vs posts directories

### App::handleAPI() - Save Endpoint
- [ ] Test requires admin auth
- [ ] Test saves content correctly
- [ ] Test validates path (no traversal)
- [ ] Test creates missing directories
- [ ] Test atomic write (temp file + rename)
- [ ] Test handles write failures
- [ ] Test validates markdown syntax (optional)

### App::resolveContentFile()
- [ ] Test resolves /about to /site/pages/about.md
- [ ] Test resolves /about to /site/pages/about.mdx
- [ ] Test resolves / to /site/pages/index.md
- [ ] Test resolves /blog/post to /site/pages/blog/post/index.md
- [ ] Test tries .mdx before .md
- [ ] Test searches pages/ before posts/
- [ ] Test returns null for non-existent content
- [ ] Test normalizes trailing slashes

### App::render()
- [ ] Test renders page with correct theme
- [ ] Test passes correct data to theme
- [ ] Test includes admin assets when authenticated
- [ ] Test injects admin UI when authenticated
- [ ] Test handles missing theme gracefully
- [ ] Test handles missing layout.php
- [ ] Test handles missing view.php
- [ ] Test merges component assets correctly
- [ ] Test handles parser errors

### App::sanitizeFilename()
- [ ] Test lowercases filename
- [ ] Test replaces spaces with hyphens
- [ ] Test removes special characters
- [ ] Test removes multiple consecutive hyphens
- [ ] Test trims leading/trailing hyphens
- [ ] Test returns 'upload' for empty string
- [ ] Test handles unicode characters
- [ ] Test handles very long filenames (255+ chars)

### App::recursiveRemoveDirectory()
- [ ] Test removes empty directory
- [ ] Test removes directory with files
- [ ] Test removes nested directories
- [ ] Test handles symlinks (should not follow)
- [ ] Test returns false for non-directory
- [ ] Test handles permission errors
- [ ] Test doesn't remove . or .. entries

### Email Sending
- [ ] Test sendContactEmail() composes email correctly
- [ ] Test loads email template
- [ ] Test replaces template variables
- [ ] Test validates email addresses
- [ ] Test prevents header injection
- [ ] Test handles missing admin email
- [ ] Test rate limiting works
- [ ] Test returns success/failure

## Parser.php Findings

(To be filled after audit)

## Auth.php Findings

(To be filled after audit)

## Admin.php Findings

(To be filled after audit)
