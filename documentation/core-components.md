# Core Components

## purpose

- Core components provide the primitives that the CMS exposes to Markdown and themes (`Block`, `Nav`, `Mermaid`, etc.).
- They live in `app/core/components/` to ensure updates ship them atomically.

## anatomy

- Each class exposes static `render()` and optionally `getAssets()`.
- Use `Parser::mergeAssets()` to collect styles/scripts that the layout outputs.
- Components must sanitize their output (the parser wraps them in placeholders before Markdown).

## extending

- Modify `app/core/components/` only when adding foundational behavior.
- For user-level extensions, copy to `content/components/` and register assets there.
