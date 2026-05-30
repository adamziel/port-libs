# Application Current Smoke Option Import

## Scope

- Added `SQLiteCurrentSmokePlan`, an unsuffixed stable helper that summarizes the current pure-PHP `wp_options` import transaction path.
- Covered Application-facing commit, statement-error, and fail-on-error rollback behavior through `SQLiteImportTransactionErrorYieldPlan`.
- Added `examples/application-current-smoke-option-import.php` with a local `--self-test` path.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCurrentSmokePlanTest.php`
  - `1 test files, 36 assertions, 0 failures`
  - 3 focused PASS lines.

## Non-Overlap

This slice avoids accepted numbered current-source consolidation work and does not create any numbered production class. It also avoids accepted WAL/VFS/B-tree/JSON table duplicate clusters by adding a Application smoke wrapper around the existing transaction-yield import path.

## Dependency Closure

No new support component is required. The smoke reuses existing native PHP transaction lock planning and Application import transaction-yield behavior.
