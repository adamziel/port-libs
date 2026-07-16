# real-upstream-corpus-trigger-fkey-dynamic-20260531T223149Z-0

Lane: `libsqlite`
Base accepted HEAD: `457d8df75c82fef3de304d8652d979a0fd3d1346`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/lastinsert.test`
- Ported sections:
  - `lastinsert-2.1..2.4`: `last_insert_rowid()` enters an `AFTER INSERT` trigger with the outer inserted rowid, inner trigger inserts update only the trigger frame, and updates do not change it.
  - `lastinsert-3.1..3.4`: `AFTER UPDATE` triggers enter with the prior connection rowid and restore it after trigger exit.
  - `lastinsert-4.1..4.4` and `lastinsert-6.1..6.4`: INSTEAD OF view INSERT/UPDATE statements do not change the connection rowid, while inserts inside the trigger frame are visible to later trigger statements.
  - `lastinsert-5.1..5.4`: `BEFORE DELETE` trigger inserts update the trigger frame but not the outer connection rowid.
  - `lastinsert-7.1..7.6`: nested temp INSTEAD OF triggers restore inner trigger rowids to the caller trigger frame and then restore the connection frame.
  - `lastinsert-8.1` and `lastinsert-9.1`: 64-bit rowids remain the last insert rowid after an AFTER trigger body runs.

## Behavior

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::lastInsertRowidTriggerFrames()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicLastInsertRowid20260531Test.php`.
- The test adds `1407` focused TestRunner PASS cases and `26226` behavior assertions over real upstream `lastinsert.test` trigger-frame behavior.
- Updated `lane-status.json` from `4042888` to `4044295` selected PASS cases. Mapped coverage remains `1589 / 1589` because the upstream denominator is already fully mapped.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamTriggerFkeyDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicLastInsertRowid20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicLastInsertRowid20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicLastInsertRowid20260531Test.php`
  - `1 test files, 26226 assertions, 0 failures`
  - PASS cases: `1407`
- `php tools/run-tests.php $(rg -l "SQLiteUpstreamTriggerFkeyDynamicPlan" lanes/libsqlite/tests | sort)`
  - `12 test files, 91202 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This does not repeat accepted trigger count-changes, `fkey2` cascade/action,
`fkey5` check, `fkey8` journal, `trigger1` schema/raise/datatype, `trigger2`
conflict propagation, `triggerG` recursive OP_Once, `e_fkey` section-6
MATCH/depth, WAL, VFS, B-tree, JSON, PRAGMA, or source-neutral cleanup
clusters. It owns only `lastinsert.test` trigger-frame
`last_insert_rowid()` restoration and wide-rowid preservation.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local upstream
trigger/FK dynamic plan surface and the hydrated SQLite source cache.

## Root Harness

Not run - isolated micro-slice.
