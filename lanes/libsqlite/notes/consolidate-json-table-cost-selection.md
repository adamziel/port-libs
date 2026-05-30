# JSON Table Cost Selection Consolidation

2026-05-29 isolated lane `consolidate-final-numbered-methods-json-table-eleventh-pass`.

## Change

- Added `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostSelectionPlan()` as the stable canonical entry point for generated-path rowid cost selection.
- Migrated the final direct `next1001-1016` JSON-table test/example to stable file names and stable payload keys.
- Preserved behavior assertions for dependency reporting, reader policy selection, current/next generated path exposure, replan reasons, point-cost reuse, and stable-source reuse.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php && php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php && php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-selection.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSelectionTest.php` passed: `1 test files, 9 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-selection.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. This reuses the existing JSON table, JSON path, generated-path rowid yield guard, and current-source cost selection helpers.

## Non-Overlap

This is consolidation-only cleanup for the JSON table cost-selection suffix family. It does not add or duplicate accepted JSON table cursor/source/hidden/visible constraint behavior.
