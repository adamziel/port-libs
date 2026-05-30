# attach-wal-temp-view-trigger-current-next81

## Behavior

This slice adds bounded trigger-body `SELECT ... FROM ...` read-after-write routing to `SQLiteAttachTempWalViewTriggerPlan`.

SQLite trigger statements execute sequentially on one connection. A later body `SELECT` that reads a table already written by an earlier body statement must observe the trigger-local next image, not the pre-trigger current snapshot. The planner now records the resolved table/schema for simple trigger-body `SELECT ... FROM table [WHERE ...]` statements and routes those reads to:

- `next` with prior journal `wal` for attached/main WAL writes;
- `connection-local-next` with prior journal `temp-rollback` for temp writes;
- `next` with prior journal `rollback` for rollback-journal fallback writes.

No-FROM trigger diagnostics remain current-snapshot reads, preserving the accepted current-next50 route behavior.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempViewTriggerCurrentNext81Test.php`
  - `1 test files, 59 assertions, 0 failures`
- Adjacent regression check:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalViewTriggerRouteCurrentNext50Test.php lanes/libsqlite/tests/SQLiteAttachTempWalViewTriggerCurrentNext48Test.php lanes/libsqlite/tests/SQLiteAttachWalTempViewCacheCurrentNext51Test.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaTriggerCurrentNext52Test.php lanes/libsqlite/tests/SQLiteAttachWalTempViewCollationCurrentNext54Test.php lanes/libsqlite/tests/SQLiteAttachTempViewTriggerYieldCurrentNext38Test.php lanes/libsqlite/tests/SQLiteAttachWalTempViewTriggerCurrentNext81Test.php`
  - `7 test files, 548 assertions, 0 failures`
- Application smoke:
  - `php lanes/libsqlite/examples/application-attach-wal-temp-view-trigger-current-next81.php`
  - Reports main WAL and temp bridge trigger routes where post-write SELECTs read `next` / `connection-local-next` with `readAfterWrite=true`.

## Non-overlap

This does not repeat accepted ATTACH WAL/temp schema cache invalidation, schema-cookie routing, temp rollback routing, trigger resolution, JSON, B-tree, VFS, or WAL byte-truncation work. The new surface is sequential read-after-write visibility for table-reading SELECT statements inside an already planned ATTACH/temp/WAL trigger body.

## Dependency closure

No new support component is needed. This reuses existing native PHP schema catalog, trigger-yield parsing, WAL append current/next planning, and temp/rollback routing components.
