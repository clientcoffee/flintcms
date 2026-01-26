# Core Components

## What core components are

Core components are the built-in building blocks shipped with Flint. Use them for common features before reaching for custom code.

## Core component list

| Component | Purpose |
| --- | --- |
| Block | Render reusable markdown blocks. |
| ContactForm | Render a contact form and handle submissions. |
| Defense | Rate limiting, threat tracking, and request hardening. |
| Sitemap | Render a nested list of public pages. |

## Where they live

- Core components: `app/core/components/`
- Site components: `site/components/`
- Theme components: `site/themes/<theme>/`

## Override behavior

Theme and site components override core components when they share a name.

## Example usage

```markdown
<Block name="nav" />
<Sitemap />
<ContactForm fields="contact-form" />
```
