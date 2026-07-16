# Attach WAL temp schema-cache current-next77

Slice: `attach-wal-temp-schema-cache-current-next77`.

This slice adds `SQLiteAttachWalTempSchemaCacheCurrentNext77Plan`, a bounded current/next bridge from ATTACH WAL/temp transaction schema-cookie outcomes into prepared statement schema-cache expiry.

Behavior covered:

- committed schema-cookie writes from main and attached schemas expire the next prepared-statement cache;
- `ROLLBACK TO` inside the transaction removes temp/archive schema writes from committed current/next cache invalidation;
- active read statements continue their current snapshot and surface `SQLITE_SCHEMA` only on reset/reprepare;
- read statements are retryable after reprepare while write statements are blocked before retry;
- full transaction rollback leaves statement cache stable.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalTempSchemaCacheCurrentNext77Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-attach-wal-temp-schema-cache-current-next77.php --self-test
application-attach-wal-temp-schema-cache-current-next77 self-test passed
```

Status delta:

- `phpPass`: `28917 -> 28976` (+59 focused PASS lines verified locally)
- mapped upstream coverage unchanged at `464 / 1589`; this is behavior-backed ATTACH/WAL/temp schema-cache coverage over already mapped inventory.

Non-overlap:

This avoids accepted ATTACH WAL/temp rollback routing, prepared-statement lifecycle expiry, transaction schema-cookie visibility, schema-trigger cache reprepare, SQL/file-control current-next parsing, WAL reader-pin handoffs, VFS writer/sync/lock clusters, JSON table source/cursor/constraint work, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is the committed-versus-rolled-back transaction boundary feeding the next schema-cache invalidation decision.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP ATTACH schema-cache, transaction current/next, and statement lifecycle planners.
