# real-upstream-corpus-trigger-fkey-dynamic-20260531T081907Z-0

Base accepted HEAD: `b9873c852a7f5b8dd171221d5d3abd96ee2031c8`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections:
  - `e_fkey-52.1..52.6`: `ON UPDATE` FK actions only fire when the parent key is distinct after parent affinity and collation are applied.
  - `e_fkey-53.1..53.3`: `ON UPDATE SET NULL` skips equal parent-key updates and rewrites child keys only after a distinct parent-key change.

## Behavior

- Added `SQLiteDynamicTriggerForeignKeyPlan::parentUpdateDistinctActionPlan()`.
- Models composite parent-key updates with parent affinity, parent collation, child reference matching, `CASCADE`, `SET NULL`, and post-update violation checks.
- Covers the upstream `NOCASE` equality case where `abc` to `aBc` does not cascade, integer-affinity text equivalence where `1` and `'1'` do not cascade, storage-class-distinct no-affinity updates where `1` to `'1'` does cascade, `NULL` propagation, and SET NULL no-op/distinct-key behavior.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentDistinct20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentDistinct20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentDistinct20260531Test.php`
  - `1 test files, 8506 assertions, 0 failures`
  - PASS cases: `1002` (`1000` dynamic behavior cases plus source-citation and ownership cases)
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentDistinct20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicParentComparison20260531Test.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php`
  - `3 test files, 13982 assertions, 0 failures`

## Countability

- Focused selected movement: `+1002` TestRunner PASS cases and `8506` behavior assertions from real upstream `e_fkey.test` sections.
- Mapped denominator remains `1589 / 1589`; this is behavior growth over an already mapped upstream trigger/FK corpus file.

## Non-Overlap

This avoids accepted `e_fkey-31/32/36/37/38/39/42/44/45/46/47/51`,
`fkey1` through `fkey8` action/check/count/savepoint batches,
`trigger1` lifecycle/program-restriction batches, `trigger2` timing/conflict
batches, `triggerC`, `triggerG`, temp-trigger, trigger-upfrom, PRAGMA FK
catalog, schema reparse, VFS/WAL, B-tree, JSON, SELECT, and suite metadata
surfaces. The owned surface is specifically `e_fkey-52` and `e_fkey-53`
parent-key distinctness before ON UPDATE action dispatch.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and the hydrated SQLite upstream checkout as source truth.
