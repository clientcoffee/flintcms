# Flint Specification

## 1. Philosophy: "The Drop-in CMS"

Flint is an opinionated, flat-file publishing engine designed for the text-first web. It rejects the complexity of databases, build pipelines, and administrative backends in favor of the file system as the single source of truth.

*   **Elegant:** Content is written in Markdown. Design is separated from logic.
*   **Deadly Simple:** No installation wizard. No database. Upload the files, and it works.
*   **Secure:** Zero-trust architecture.

## 2. Architecture

*   **Stack:** PHP 8.2+ (Strict Types).
*   **Storage:** Flat-file system.
*   **Deployment:** FTP, SFTP, Git, or rsync.
*   **State:** Stateless. The file system is the state.

## 3. Core Concepts

### 3.1 Content (The Writer)
Content lives in the `/content` directory.
*   **Format:** Markdown with Frontmatter (YAML).
*   **Routing:** 1:1 mapping between file structure and URL.
    *   `/content/index.md` → `/`
    *   `/content/blog/post-1.md` → `/blog/post-1`
*   **MDX-Lite:** We support a strict subset of "Components" inside Markdown using a custom parser.
    ```markdown
    # Hello World
    <Callout type="alert">This is a dynamic module.</Callout>
    ```

### 3.2 Themes (The Designer)
Themes live in `/themes/{theme_name}`.
*   **Role:** Pure presentation.
*   **Constraint:** Themes contain **no business logic**. They receive data objects and render HTML.
*   **Structure:**
    *   `layout.php`: The outer shell (HTML head, nav, footer).
    *   `view.php`: The content container.
    *   `404.php`: Error page.

### 3.3 Modules (The Developer)
Modules live in `/modules`.
*   **Role:** Encapsulated logic and functionality.
*   **Behavior:** Modules are PHP classes that map to Markdown tags.
*   **Example:** `<ContactForm />` in Markdown triggers `Modules\ContactForm::render()`.

## 4. Configuration
There is no Admin Panel. Configuration is defined in `config.php`.

```ini
[site]
name = "My Flat Blog"
theme = "minimalist"

[mail]
admin_email = "me@example.com"
smtp_host = "smtp.provider.com"
```

## 5. Security: The "Zero Attack Vector" Goal

Since there is no database, SQL injection is impossible. The focus shifts to Input Validation and Remote Code Execution (RCE).

### 5.1 The "Read-Only" Principle
*   The CMS core never writes to the `/content` or `/themes` directories.
*   The only write permission required is for `/cache` (if enabled).
*   **Benefit:** Even if an exploit is found, the attacker cannot deface the site permanently or inject backdoors into source files.

### 5.2 Form Handling (The "No-Store" Policy)
*   **Submission:** Forms (like Contact) are POSTed to the server.
*   **Processing:** Data is sanitized, validated, and immediately dispatched via SMTP to the `admin_email`.
*   **Storage:** **Zero.** Form data is never saved to disk. This eliminates PII (Personally Identifiable Information) liability and data leak risks.
*   **Protection:**
    *   **CSRF:** Cryptographically secure, one-time-use nonces required for every submission.
    *   **Honeypot:** Invisible fields to trap bots without user friction.

### 5.3 Injection Prevention
*   **XSS:** All Markdown output is escaped by default. Modules must explicitly opt-in to render raw HTML.
*   **Path Traversal:** The Router strictly validates all file paths against an allowlist of directories. `../` attempts result in an immediate 403.

## 6. Testing Strategy

To ensure stability in an open-source environment:
*   **Unit Tests:** 100% coverage for the Markdown Parser and Router.
*   **Mutation Testing:** Used to verify the quality of the tests themselves.
*   **Static Analysis:** PHPStan at max level to enforce strict typing.

## 7. Developer Experience (DX)

### Local Editing
1.  Open the folder in VS Code.
2.  Edit `content/index.md`.
3.  Save.
4.  (Optional) Run `php -S localhost:8000` to preview.

### Deployment
1.  Drag the folder to your FTP client.
2.  Upload.
3.  Done.

---

*This spec sheet represents the source of truth for the Flint PHP port.*