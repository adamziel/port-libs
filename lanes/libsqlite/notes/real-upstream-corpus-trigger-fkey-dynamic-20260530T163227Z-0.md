# Real Upstream Trigger/FK Dynamic Corpus Slice

- Base accepted HEAD: `92b65fe2933444167e639234f5a0c525e1097aec`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`.
- Ported scenario: `trigger1-1.11`, where an `AFTER UPDATE` row trigger deletes rows using `old` values without corrupting the outer update statement.
- Focused assertion delta: +650 assertions in `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`.
- Non-overlap: extends the existing dynamic trigger/FK corpus beyond accepted `fkey1.test fkey1-5.1..5.4`, `trigger1.test trigger1-1.10`, and `trigger1.test trigger1-1.2..1.8` coverage.
- Dependency closure: no new support component is needed; this reuses the existing dynamic trigger/FK planner.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php` -> `1 test files, 1836 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php` -> no syntax errors.
- `git diff --check -- lanes/libsqlite` -> passed.

Root harness: not run - isolated micro-slice.
