---
id: task-1
title: Update Dependency Injection & Container docs (Session 3)
status: Done
assignee: []
created_date: '2025-12-16 16:46'
updated_date: '2025-12-16 16:56'
labels:
  - docs
  - session-3
dependencies: []
priority: medium
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Update documentation for the container and dependency injection features to match current codebase.

Files to update:
- `docs/guides/binding-services.md`
- `docs/guides/injecting-dependencies.md`
- `docs/guides/using-scoped-bindings.md`
- `docs/reference/container-methods.md`

Focus areas:
- Verify container API against current `Container.php`
- Contextual bindings documentation
- Scoped bindings patterns
- Compare all examples against `CLAUDE.md` and source code
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 All four documentation files reviewed and updated
- [x] #2 Container API methods match current implementation
- [x] #3 Contextual binding examples are accurate
- [x] #4 Scoped binding patterns are documented correctly
- [x] #5 Code examples tested or verified against source
<!-- AC:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
## Changes Made

### binding-services.md
- Added new "Contextual Bindings" section documenting `for()` method
- Shows single and multiple context binding patterns

### injecting-dependencies.md
- Verified accurate - no changes needed
- `use function Verge\make` namespace is correct

### using-scoped-bindings.md
- Verified accurate - no changes needed
- Correctly explains bind vs singleton vs scoped lifetime

### container-methods.md
- Added `for()` to binding methods table with "(modifier)" notation
- Added explanation that `for()` is a modifier for contextual bindings
- Added `get()` to resolution methods table (PSR-11 alias)
- Added full `for()` method documentation with examples
<!-- SECTION:NOTES:END -->
