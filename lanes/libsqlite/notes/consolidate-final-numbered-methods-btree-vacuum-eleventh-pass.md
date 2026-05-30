# B-tree Vacuum Numbered Method Consolidation - Eleventh Pass

## Scope

Consolidated the B-tree vacuum pointer-map freeblock tail-range callers that still constructed generated method names such as `tableLeafFromDeleteResultNext1007` through `tableLeafFromDeleteResultNext1134`.

## Change

- Updated focused tests for ranges `1007-1134` to call `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafCurrentSourceFreelistHandoffFromDeleteResult(...)`.
- Updated matching Application examples for ranges `1007-1134` to call the same canonical entrypoint.
- Left behavior assertions intact: statuses, current-source rows, token checks, dependency closure, and non-overlap assertions still verify each slice number.

## Dependency Closure

No new support component is needed. This is caller consolidation over the existing canonical B-tree vacuum freelist handoff implementation.

## Verification

Run after edits:

- `php -l` on changed PHP files.
- Focused `php tools/run-tests.php` for changed B-tree vacuum range tests.
- Changed Application examples with `--self-test`.
- `git diff --check -- lanes/libsqlite`.
