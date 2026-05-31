# real-upstream-corpus-trigger-fkey-dynamic-20260531T150011Z-0

Lane: `libsqlite`
Base accepted HEAD: `5042ee5a640251937d88ffe1e25c7b681010f72f`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- Ported sections:
  - `trigger1-11.1`: `RAISE()` is rejected outside a trigger program with `RAISE() may only be used within a trigger-program`.
  - `trigger1-15.1`: updating an `INTEGER PRIMARY KEY` to text fails with `datatype mismatch` even when a `BEFORE UPDATE` trigger exists.
  - `trigger1-15.2`: inserting text into the `INTEGER PRIMARY KEY` column fails with `datatype mismatch`, while the previous valid row image remains intact.

## Behavior

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::trigger1RaiseDatatypeCorpus()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger1RaiseDatatype20260531Test.php`.
- The test file adds `1002` focused TestRunner PASS cases and `8264` behavior assertions over the real upstream `trigger1.test` sections.
- Mapped coverage remains `1589 / 1589`; this is PASS-line and assertion growth over already mapped upstream corpus inventory.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1RaiseDatatype20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1RaiseDatatype20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1RaiseDatatype20260531Test.php`
  - `1 test files, 8264 assertions, 0 failures`
  - PASS cases: `1002`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1RaiseDatatype20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1LateRegression20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCLateDiagnostics20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 17816 assertions, 0 failures`
- `php -r '$j=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($j)) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This does not repeat accepted trigger1 temp-trigger reinstall/body rebind (`trigger1-10.*`), trigger1 schema lifecycle/create diagnostics (`trigger1-1.*` and `trigger1-2.*`), name catalog (`trigger1-6.*` and `trigger1-8.*`), statement preservation (`trigger1-1.10..1.11`), target class validation (`trigger1-1.12..1.14`), trigger-program DML restrictions (`trigger1-16.*`), late regressions (`trigger1-17.*..24.*`), triggerC late diagnostics (`triggerC-16.*..17.*`), temptrigger shared-cache/attached-chain behavior, fkey action/check/defer families, JSON, VFS/WAL, B-tree, PRAGMA, or source-neutral cleanup.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local upstream trigger/FK corpus plan surface and the hydrated upstream SQLite checkout as source truth.
