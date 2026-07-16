real-upstream-corpus-trigger-fkey-dynamic-20260530T212635Z-0

Base accepted HEAD: 551608c47b9b5c9b4c74afdd6349b99f03720fcd

Scope:
- Added a real upstream trigger/FK dynamic matrix test for SQLite upstream
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`.
- Ported the `trigger2-2.$ii-before` and `trigger2-2.$ii-after` trigger
  program execution matrix into generic PHP TestRunner cases using
  `SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution()`.
- Covered BEFORE/AFTER timing across INSERT, UPDATE, DELETE statements and the
  upstream trigger-program families:
  `UPDATE tbl SET b = old.b`,
  `INSERT INTO log VALUES(new.c, 2, 3)`,
  `DELETE FROM log WHERE a = 1`,
  compound insert/update/delete trigger body, and
  `INSERT INTO log SELECT * FROM tbl`.

Focused evidence:
- Red-first command initially failed because the test used shorthand trigger
  program labels not accepted by the existing generic helper:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerProgramMatrixTest.php`
  reported `1 test files, 3 assertions, 17137 failures`.
- Fixed the test to use the existing upstream-shaped trigger program labels and
  exact helper semantics.
- Final focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerProgramMatrixTest.php`
  passed with `1 test files, 22684 assertions, 0 failures`.

Countability:
- Adds one new focused PHP test file.
- Adds 1,261 distinct TestRunner cases and 22,684 behavior assertions/PASS
  lines from a real hydrated upstream SQLite trigger file.
- Expected dashboard movement: focused PHP PASS-line growth only; no mapped
  denominator growth and no full release/all runner parity claim.

Non-overlap:
- Does not repeat the already accepted trigger/FK statement-preservation,
  defer-pragma, action-journal, trigger2 batch, trigger4 view, trigger5 undo,
  variable-rejection, recursive triggerG, fkey7, fkey8, or foreign-key check
  files. This slice owns `trigger2.test` section 2's BEFORE/AFTER trigger
  program matrix only.

Dependency closure:
- No new support component is needed. The test reuses the existing generic
  `SQLiteDynamicTriggerForeignKeyPlan` trigger-program executor and the
  hydrated upstream SQLite test checkout as source truth.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerProgramMatrixTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerProgramMatrixTest.php`
  passed with `1 test files, 22684 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run; isolated micro-slice.
