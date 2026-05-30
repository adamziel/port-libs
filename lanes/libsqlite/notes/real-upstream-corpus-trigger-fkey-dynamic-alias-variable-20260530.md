# real-upstream-corpus-trigger-fkey-dynamic-alias-variable-20260530

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T234953Z-0`

Base accepted HEAD: `8c54cf5d7498c37ac92862dd579a0f2d540ceb41`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerD.test`
  - `triggerD-1.1..1.4`: ordinary `rowid`, `oid`, and `_rowid_` columns shadow storage rowid in trigger `OLD`/`NEW` references.
  - `triggerD-2.1..2.4`: without shadow columns, trigger `OLD`/`NEW` rowid aliases resolve to the storage rowid.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerE.test`
  - trigger variable creation rejection text and `triggerE-2.1..2.3`: variables in trigger definitions loaded from schema resolve to SQL NULL.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerRowidAliasResolution()` for triggerD alias behavior.
- Added `SQLiteDynamicTriggerForeignKeyPlan::storedTriggerVariablesResolveNull()` for triggerE stored trigger variable behavior.
- Added focused dynamic corpus test coverage with 13,691 assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicAliasVariableTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicAliasVariableTest.php`
  - `1 test files, 13691 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing trigger/FK dynamic planner model and hydrated upstream SQLite corpus files.

Root harness: not run - isolated micro-slice.
