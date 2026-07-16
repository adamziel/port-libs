# Trigger RETURNING Source Admission Method Consolidation

## Scope

- Consolidated the trigger recursive-view RETURNING source-admission block in `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan`.
- Replaced the numbered public entry point and private helpers with descriptive unsuffixed method names.
- Renamed the direct focused test and Application smoke from the numbered source-admission filename to stable unsuffixed filenames.
- Removed the numbered status and dependency marker strings for the touched scenario.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceAdmissionTest.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-source-admission.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningSourceAdmissionTest.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-source-admission.php --self-test`
  - `application-trigger-recursive-view-returning-source-admission self-test passed`

## Dependency Closure

No new support component is needed. This is a production helper/method-name consolidation of existing native PHP trigger RETURNING behavior.
