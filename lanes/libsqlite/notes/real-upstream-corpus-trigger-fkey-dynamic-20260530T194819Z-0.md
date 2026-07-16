# real-upstream-corpus-trigger-fkey-dynamic-20260530T194819Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Added a real upstream `fkey8.test` action-journal dynamic corpus slice. The
ported sections are:

- `fkey8.test` `1.2.1..1.5.3`: `ON DELETE CASCADE`, `ON DELETE SET NULL`, and
  `ON DELETE SET DEFAULT` statement-journal and child-key rewrite behavior.
- `fkey8.test` `1.6.1..1.6.4`: `ON UPDATE CASCADE`, `ON UPDATE SET NULL`, and
  `ON UPDATE SET DEFAULT` statement-journal and child-key rewrite behavior.
- `fkey8.test` `7.1..7.3`: attached-schema `UPDATE ... SET pid = pid * 10`
  cascade propagation.

Non-overlap: this does not repeat accepted `fkey2` nocase repair,
deferred-savepoint trigger/FK coverage, trigger2 view trigger rows, composite
cascade, or source-neutral cleanup. The new helper is generic and lives in the
existing `SQLiteDynamicTriggerForeignKeyPlan`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicActionJournalTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicActionJournalTest.php`
  passed: `1 test files, 7350 assertions, 0 failures`, with `7089` PASS lines.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement if accepted: `phpPass` `469812 -> 476901`
(`+7089` PASS lines). Mapped coverage remains `1472 / 1589`.

Dependency closure: no new support component is needed; this reuses the
existing lane-local trigger/FK row-array planner and real upstream hydrated
SQLite corpus files.
