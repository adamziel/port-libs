# Real Upstream Trigger/FK Dynamic NOCASE Repair

Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`

Owned upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-12.2.1` through `fkey2-12.2.4`

Behavior added:

- `AFTER DELETE` trigger reinserts deleted parent keys when existing child rows reference those keys under NOCASE comparison.
- `NO ACTION` foreign keys can be repaired by the trigger program before the statement completes.
- `ON DELETE RESTRICT` fails immediately before the trigger repair can run.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`: no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`: no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicNocaseRepairTest.php`: `1 test files, 2084 assertions, 0 failures`
- Selected PASS lines: `2002`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`: not run, guard file is not present in this worktree
- `git diff --check -- lanes/libsqlite`: passed

Non-overlap:

- This does not repeat accepted trigger RAISE, trigger2 selective/update/view, foreign-key action matrix, deferred savepoint, or returning/upsert trigger coverage.
- The new source path is a bounded extension of `SQLiteDynamicTriggerForeignKeyPlan` for the fkey2 NOCASE delete-trigger repair behavior.

Dependency closure:

- No new support component is required. The slice reuses lane-local array execution helpers and the hydrated upstream SQLite test checkout as source truth.
