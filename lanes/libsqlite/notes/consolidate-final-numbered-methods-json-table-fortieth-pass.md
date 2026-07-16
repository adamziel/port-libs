# JSON Table Numbered Method Consolidation Fortieth Pass

Scope: consolidated the private rowid-limit helper family behind
`SQLiteJsonTablePlan::currentSourceGeneratedPathRowidAliasLimit()` by removing
the worker-number suffix from the production helper method names.

Behavior: unchanged. Existing direct tests still call the stable public entry
point and assert the legacy payload keys/opcodes used by current handoff
coverage.

Dependency closure: no new support component needed; this reuses the existing
native JSON table generated-path rowid limit admission planner.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext219Test.php` -> `1 test files, 52 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.
