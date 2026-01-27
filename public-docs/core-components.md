# Core Components

## What core components are

Core components are the minimal building blocks shipped directly with Flint so a default install works out of the box. Optional components live in the `flint-components` registry and can be installed later.

## Core component list

| Component | Purpose |
| --- | --- |
| Block | Render reusable markdown blocks. |
| ContactForm | Render a contact form and handle submissions. |
| Nav | Render navigation output for themes. |
| Sitemap | Render a nested list of public pages. |

## Where they live

- Core (shipped) components: `site/components/`
- Optional components: install into `site/components/`
- Theme components: `site/themes/<theme>/`

## Override behavior

Theme components override site components when they share a name.

## Example usage

```markdown
<Block name="nav" />
<Sitemap />
<ContactForm fields="contact-form" />
```
