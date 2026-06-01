# real-upstream-corpus-trigger-fkey-dynamic-20260601T003340Z-0

Status: focused upstream-backed trigger/FK corpus growth for SQLite foreign-key
`CREATE TABLE` validation.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
  - `e_fkey-54.1` through `e_fkey-54.B`
  - Parent table/key validity is not checked at `CREATE TABLE` time.
  - Child key column existence and explicit child/parent key arity are checked.
  - `PRAGMA foreign_keys` `ON` and `OFF` produce the same create-table result.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCreateTableValidationPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicCreateTableValidation20260601Test.php`
  with `125` dynamic generic application variants over the nine upstream cases,
  run under both foreign-key pragma states plus aggregate checks.
- Red-first evidence: the new focused test initially failed after the upstream
  citation checks because the new plan method was absent.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCreateTableValidation20260601Test.php`
- Result: `1 test files, 45007 assertions, 0 failures`
- PASS cases added by the focused file: `2503`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCreateTableValidation20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDefinitionDiagnosticTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `4 test files, 56364 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  and
  `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCreateTableValidation20260601Test.php`
  both reported no syntax errors.
- `git diff --check -- lanes/libsqlite` reported no whitespace errors.

Non-overlap:

- This owns `e_fkey.test` `e_fkey-54.*` create-table definition validation.
- It avoids accepted `fkey2-10.*` DML-time mismatch diagnostics, `fkey2-14.*`
  ALTER/DROP behavior, `e_fkey-57..61` implicit DROP TABLE behavior,
  `e_fkey-62..64` section-six limits/recursive action behavior, trigger
  program recursion, and existing FK action/count-change/DDL clusters.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  dynamic trigger/FK plan surface and upstream-cache citation convention.

Root harness:

- Not run; this is an isolated micro-slice.
