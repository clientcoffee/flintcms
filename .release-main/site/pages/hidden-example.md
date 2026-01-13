---
title: "Hidden Example Page"
description: "This page is published but hidden from navigation"
status: hidden
icon: eye
---

# Hidden Page

This page is published and accessible via direct link, but marked as hidden from navigation.

## Use Cases

Hidden pages are useful for:

- Landing pages for campaigns
- Thank you pages after form submission
- Documentation that should be accessible but not prominent
- Pages shared via direct links only

## Behavior

- **Accessible**: Anyone with the URL can view this page
- **Not listed**: Won't appear in site navigation or page listings
- **Badge shown**: Admins see a gray "Hidden from Navigation" badge
- **Default**: If no status is provided, pages are published by default.

To make this page visible in navigation, change the status to:
```
status: published
```
