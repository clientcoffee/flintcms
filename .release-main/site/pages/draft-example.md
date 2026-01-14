---
title: "Draft Example Page"
description: "This page is a draft and not accessible to non-admin users"
status: draft
icon: warning
---

# Draft Page

This is a draft page. It should only be visible to administrators.

Non-admin users will see a 404 error when trying to access this page.

## Features

- Only accessible to logged-in admins
- Shows a yellow "Draft" badge for admins
- Returns 404 for public users
- Can be edited and previewed before publishing

To publish this page, change the frontmatter to:
```
status: published
```
