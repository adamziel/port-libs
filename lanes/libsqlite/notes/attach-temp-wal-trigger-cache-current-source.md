# attach-temp-wal-trigger-cache-current-source

Status: isolated behavior-backed libsqlite slice for prepared trigger-program cache invalidation across current and next schema sources.

## Behavior

- Adds `SQLiteAttachWalTempViewCachePlan::triggerProgramCacheCurrentSourceNext()`.
- Captures prepared trigger program entries before schema changes, applies next schema records, then reports whether the next execution must reprepare.
- Keeps active current trigger programs usable while routing next reprepare decisions through trigger SQL, target table/view roots, body-table dependencies, schema-cookie WAL metadata, and main/temp/attached schema scope.
- Non-temp trigger bodies resolve unqualified body tables inside their owning schema; temp triggers can follow temp/main/attached search order.

## Application smoke

- `php lanes/libsqlite/examples/application-attach-temp-wal-trigger-cache-current-source.php --self-test`
- Result: `application-attach-temp-wal-trigger-cache-current-source self-test passed`

## Focused evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalTriggerCacheCurrentSourceTest.php`
- Result: `1 test files, 59 assertions, 0 failures`
- Expected dashboard movement: `phpPass` `31557 -> 31616` from the 59 new focused PASS lines. Mapped upstream coverage remains `465 / 1589`; this is focused PHP behavior for an already mapped ATTACH/temp/WAL trigger-cache surface.

## Non-overlap

Avoids accepted ATTACH WAL/temp rollback routing, schema-cache view/table routing, prepared-statement lifecycle expiry, trigger RETURNING/savepoint behavior, JSON table/source/cursor constraints, VFS writer/sync/lock/rollback clusters, WAL byte/checkpoint/savepoint clusters, B-tree page/freelist/overflow clusters, SQL SELECT text/group/order/subquery clusters, and Unicode GLOB/malformed-text work. This slice is only the missing current-source to next-source prepared trigger-program cache boundary.

## Dependency closure

No new support component is needed. The slice reuses lane-local attached schema catalog records, trigger resolution, trigger body dependency parsing, and WAL schema-cookie metadata.
