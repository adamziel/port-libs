# Real Upstream Trigger/FK Dynamic Corpus

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T164641Z-0`

Base accepted HEAD: `77aaee93e1232164eda546b44d6f0e2ddd146261`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Scenarios:
  - `fkey2-4.1..4.4`: recursive foreign-key cascade actions still recurse when `PRAGMA recursive_triggers` is off.
  - `fkey2-12.2.1..12.2.4`: AFTER DELETE trigger can repair parent rows unless `ON DELETE RESTRICT` blocks before the trigger repair.
  - `fkey2-12.3.1..12.3.5`: composite foreign key cascade maps child columns to the default parent primary-key column order.

## Patch

- Extended `SQLiteDynamicTriggerForeignKeyPlan` with generic native behavior models for recursive FK cascade versus trigger recursion, RESTRICT/trigger reinsertion, and composite cascade column mapping.
- Extended `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php` with 1310 new focused PASS lines.
- No new support component is needed; the patch reuses the existing native trigger/FK dynamic corpus helper.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - Result: `1 test files, 3146 assertions, 0 failures`
  - Previous file size before this slice: 1836 assertions
  - New focused assertion/PASS-line delta: `+1310`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`

The legacy domain-specific guard path is not present in this worktree; the current generic API guard is `SQLiteNoDomainSpecificApiTest.php`.

Root harness: not run - isolated micro-slice.
