# Real Upstream Trigger/FK Dynamic Slice

Accepted base: `25bfd8b5291a9dba8331a5a3b17363ea2ce51f4a`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported scenarios: `triggerC-1.11`, `triggerC-1.12`, `triggerC-1.13`, `triggerC-1.14`, `triggerC-1.15`

Implemented:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerCUpdateConflictRollbackPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTriggerCUpdateConflictRollback20260601Test.php`.
- The new planner models `UPDATE OR REPLACE` recursive `AFTER UPDATE` rollback, same-rowid `UPDATE t6 SET a=a` trigger success, and `UPDATE OR ROLLBACK` unique conflict rollback that unwinds prior trigger counter side effects.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCUpdateConflictRollback20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCUpdateConflictRollback20260601Test.php`
- `php -r '$tests = require "lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCUpdateConflictRollback20260601Test.php"; echo count($tests), PHP_EOL;'`
  - `9405`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCUpdateConflictRollback20260601Test.php`
  - `1 test files, 9417 assertions, 0 failures`
- `set -o pipefail; php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteRealUpstreamTriggerFkeyDynamicTriggerC.*Test\.php$' | sort) | tail -n 5`
  - `11 test files, 85730 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php | tail -n 5`
  - `1 test files, 5 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json ok\n";'`
  - `lane-status.json ok`
- `git diff --check -- lanes/libsqlite`
  - no output

Dashboard-local delta:

- `phpPass`: `5792118 -> 5801523` (`+9405` focused TestRunner PASS cases from the new test file).
- Focused behavior assertions: `9417` in the new test file.
- Mapped upstream denominator: unchanged; this is additional behavior coverage within already mapped `triggerC.test`.

Non-overlap:

- This slice covers `triggerC.test triggerC-1.11..1.15` conflict and rollback ordering.
- It does not repeat existing `triggerC-1.2..1.10` OLD/NEW lifecycle coverage, `triggerC-13.*` recursion-limit counters, `triggerC-9.*` indexed delete cascade coverage, or active-scan trigger-drop coverage.

Dependency closure:

- No new support component is needed. The slice reuses the existing lane-local dynamic trigger/fkey planner and TestRunner harness.

Root harness:

- Not run - isolated micro-slice.
