# Consolidate Final Numbered Production Suffix Cleanup Sixty-Second Pass

## Summary

- Confirmed the exact user-named 150 production, test, and example suffix is absent from `lanes/libsqlite/src`, `lanes/libsqlite/tests`, and `lanes/libsqlite/examples`.
- Consolidated the JSON table generated-path rowid cost helper family by replacing numbered production entry methods with stable descriptive entry points:
  - `currentSourceGeneratedPathRowidBatchedXNextPlan()`
  - `currentSourceGeneratedPathRowidFinalCostPlan()`
  - `currentSourceGeneratedPathRowidResumeCheckpointPlan()`
  - `currentSourceGeneratedPathRowidYieldGuardPlan()`
  - `currentSourceGeneratedPathRowidXColumnYieldPlan()`
  - `currentSourceGeneratedPathRowidPinnedSourcePlan()`
- Renamed the direct focused tests and WordPress examples to stable descriptive filenames and migrated their direct calls.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l` for each renamed focused test/example file
- `php tools/run-tests.php` for the six renamed focused JSON table generated-path rowid tests
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-*.php --self-test` for the six renamed examples
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed; this slice only renames/consolidates existing native JSON table planner entry points.
