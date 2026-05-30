# ATTACH Schema Cache Invalidation Current Next34

This slice adds generation-stamped schema lookup caching to
`SQLiteAttachedSchemaCatalog` and invalidates it whenever ATTACH, DETACH, or
schema-record replacement changes the current schema graph.

Behavior covered:

- Cached table and index lookups record hits/misses without changing SQLite
  temp/main/attached search order.
- ATTACH invalidates cached misses so newly attached Application tables and
  indexes become visible immediately.
- DETACH invalidates cached attached winners so unqualified lookup either
  falls through to the next attached database or returns no row.
- `replaceSchemaRecords()` invalidates cached main/temp/attached table and
  index results, modeling schema-cookie reparse after DDL or schema reload.
- Built-in `sqlite_schema` aliases bypass ordinary object cache entries and
  retain their main/temp/schema-qualified semantics.
- Direct and table-valued schema PRAGMAs reuse the invalidated current-source
  lookup path.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachSchemaCacheInvalidationCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
45 PASS lines
1 test files, 142 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachDetachSchemaCurrentNext18Test.php lanes/libsqlite/tests/SQLiteAttachSchemaCacheInvalidationCurrentNext34Test.php
Focused test run: 2 selected test files (root lock skipped)
85 PASS lines
2 test files, 222 assertions, 0 failures

$ php lanes/libsqlite/examples/application-attach-schema-cache-invalidation-current-next34.php
Application smoke reported cached miss before ATTACH, attached `network`
`wp_sitemeta` at root page 12 after ATTACH, and no stale row after DETACH.
```

Changed PHP syntax checks:

```text
$ php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
No syntax errors detected in lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
$ php -l lanes/libsqlite/tests/SQLiteAttachSchemaCacheInvalidationCurrentNext34Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteAttachSchemaCacheInvalidationCurrentNext34Test.php
$ php -l lanes/libsqlite/examples/application-attach-schema-cache-invalidation-current-next34.php
No syntax errors detected in lanes/libsqlite/examples/application-attach-schema-cache-invalidation-current-next34.php
```

Dashboard delta:

- `phpPass`: `11752 -> 11797` from the 45 newly passing focused TestRunner
  cases in `SQLiteAttachSchemaCacheInvalidationCurrentNext34Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this is a focused native behavior
  slice, not a new upstream inventory mapping.

Non-overlap:

This avoids accepted ATTACH temp/VFS open planning, attach temp collation
resolution, PRAGMA database/table-list cataloging, JSON table source/cursor
work, VFS writer/locking/sync work, WAL transaction/checkpoint/savepoint
clusters, and B-tree page-move/freeblock/freelist clusters. The new behavior is
specifically stale schema lookup cache invalidation after ATTACH/DETACH/current
schema replacement.

Dependency closure:

No new support component is needed. The slice reuses the existing lane-local
schema catalog, schema-record, PRAGMA catalog, and ATTACH/DETACH SQL parser
primitives.
