---
id: task-4
title: Create Deployment & New Feature guides (Session 9)
status: Done
assignee:
  - docs-writer
created_date: '2025-12-16 16:46'
updated_date: '2025-12-16 17:04'
labels:
  - docs
  - session-9
  - new-feature
dependencies: []
priority: high
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Update deployment documentation and create new guides for currently undocumented features.

Files to update:
- `docs/guides/deploying-frankenphp.md`

New files to create:
- `docs/guides/using-console.md` - CLI commands (routes:list, cache:warm, cache:clear)
- `docs/guides/bootstrap-caching.md` - Production caching (BootstrapCache, CachedRouter)

These guides document features that exist in the codebase but have no documentation.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 deploying-frankenphp.md reviewed and updated
- [x] #2 docs/guides/using-console.md created with CLI commands documentation
- [x] #3 docs/guides/bootstrap-caching.md created with BootstrapCache documentation
- [x] #4 Console commands (routes:list, cache:warm, cache:clear) explained with examples
- [x] #5 Bootstrap caching workflow for production explained
- [x] #6 CachedRouter usage documented
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Session 10: All guides already existed and were comprehensive. Added Pre-Warming Caches section to deploying-frankenphp.md with link to bootstrap-caching guide.
<!-- SECTION:NOTES:END -->
