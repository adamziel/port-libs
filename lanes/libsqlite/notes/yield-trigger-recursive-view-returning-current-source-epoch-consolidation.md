# Trigger Recursive View RETURNING Current Source Epoch Consolidation

## Scope

- Consolidated the former worker-numbered current-source epoch receipt surface in `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeCurrentSourceEpochReceipt()`.
- Renamed direct test and Application smoke files from the numbered `current-source-next230` names to stable current-source epoch receipt names.
- Migrated direct result keys, option keys, status strings, dependency markers, and assertions to stable `current_source_epoch` naming.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceEpochReceiptTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-epoch-receipt.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceEpochReceiptTest.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-epoch-receipt.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The consolidated surface continues to reuse the native recursive view RETURNING current-source, following-current seal, and source-epoch receipt helpers already in the canonical production class.
