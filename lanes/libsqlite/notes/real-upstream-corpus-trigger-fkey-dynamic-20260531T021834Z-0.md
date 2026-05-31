# real-upstream-corpus-trigger-fkey-dynamic-20260531T021834Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T021834Z-0`
Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
- Ported section: `trigger2-10.1`

## Behavior

This ports the upstream `INSTEAD OF INSERT` view-trigger regression where
`INSERT INTO v2(a,d) VALUES(11,14)` fires a trigger body that writes
`new.a`, `new.b`, `new.c`, and `new.d` into the base table. The omitted view
columns must be visible as SQL NULL in the trigger NEW row, so the final base
row is `[11, NULL, NULL, 14]`.

The dynamic PHP test expands that real section over 120 deterministic insert
pairs, including explicit `a,d` inserts, optional middle columns, all-column
inserts, and single-column inserts that preserve SQL NULL for every omitted
view column.

## Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewInsertSubsetTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T021834Z-0.md`

## Non-Overlap

This does not repeat the parked broad trigger/FK action handoff, fkey2 action
matrices, fkey6/fkey7/fkey8, triggerA WHERE propagation, trigger2 row timing,
trigger2 program execution, trigger2 expression-view OLD/NEW rows, trigger9
view rowid behavior, triggerC/F/G recursive or rowid mutation behavior, or
UPSERT/RETURNING paths. The owned surface is specifically `trigger2-10.1`
view-trigger NEW-row column-subset materialization.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteDynamicTriggerForeignKeyPlan` native PHP model and hydrated upstream
SQLite corpus as source truth.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewInsertSubsetTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewInsertSubsetTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewInsertSubsetTest.php`
  - `1 test files, 1926 assertions, 0 failures`
  - Focused PASS-line delta: `1683`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicViewInsertSubsetTest.php`
  - `3 test files, 10282 assertions, 0 failures`
- Broad trigger/upsert/FK smoke:
  - Command: `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -type f \( -name '*Trigger*Test.php' -o -name '*ForeignKey*Test.php' -o -name '*Upsert*Test.php' -o -name '*Fkey*Test.php' \) | sort)`
  - Result: `570 test files, 903234 assertions, 2 failures`
  - Failures were existing PRAGMA index_xinfo/FK expectation mismatches outside this slice:
    - `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240243Test.php`: `pragma index xinfo foreignkey next241 implicit parent reference resolves primary key order`
    - `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241Test.php`: `pragma index xinfo foreignkey implicit parent reference current source next241 accepts table primary key order`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed
