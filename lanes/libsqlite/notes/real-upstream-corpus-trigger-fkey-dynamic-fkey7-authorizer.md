# real-upstream-corpus-trigger-fkey-dynamic-fkey7-authorizer

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test`
- `fkey7-1.2..1.5`: authorizer table reads required by foreign-key enforcement during parent updates.
- `fkey7-4.1..4.6`: `INSERT OR FAIL` behavior for foreign-key failure, unique failure, preserved prior successful rows, and empty `foreign_key_check`.

Implemented behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyAuthorizerReadPlan()` for the fkey7 parent/reference/dependent-table read matrix.
- Added `SQLiteDynamicTriggerForeignKeyPlan::insertOrFailForeignKeyBatch()` for conflict-policy `FAIL` batch insert behavior.
- Added focused dynamic corpus test coverage in `SQLiteRealUpstreamTriggerFkeyDynamicFkey7AuthorizerTest.php`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7AuthorizerTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey7AuthorizerTest.php`: `1 test files, 18787 assertions, 0 failures`.

Non-overlap:

- This slice does not touch the existing fkey2 nocase/composite coverage, fkey6 defer-foreign-keys coverage, trigger2 view-trigger coverage, triggerC rowid mutation coverage, trigger5 undo coverage, or action-matrix coverage.

Dependency closure:

- No new support component is needed. The slice reuses the existing dynamic trigger/FK plan helper and focused PHP test runner.
