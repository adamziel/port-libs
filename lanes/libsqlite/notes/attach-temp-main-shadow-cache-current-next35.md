# Attach/temp/main shadow cache current-next35

Slice: `yield-sqlite-attach-temp-main-shadow-cache-current-next35`

## Behavior

This slice adds bounded prepared-statement schema-cache resolution evidence for
ATTACH/DETACH cases where TEMP, main, attached schemas, and SQLite catalog
aliases can shadow each other without the SQL text changing.

- `schemaCacheResolutionSnapshot()` captures current table/index winners,
  including schema, object name, root page, and type.
- `schemaCacheResolutionInvalidation()` compares a snapshot to the current
  catalog after ATTACH/DETACH and reports changed/unchanged table and index
  names.
- Missing qualified attached-schema names are represented as unresolved cache
  entries for this snapshot path, while normal strict lookup still raises.
- Bare `sqlite_schema` remains pinned to `main` and `sqlite_temp_schema`
  remains pinned to `temp`, so catalog aliases are not falsely invalidated by
  ordinary attached table shadowing.

## Focused Evidence

```
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempMainShadowCacheCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 88 assertions, 0 failures
```

Application smoke:

```
php lanes/libsqlite/examples/application-attach-temp-main-shadow-cache-current-next35.php --self-test
application-attach-temp-main-shadow-cache-current-next35 self-test passed
```

## Dashboard Delta

- `phpPass`: `12271 -> 12359` for the 88 newly verified focused assertions.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged at `461 / 1589`; this is native PHP
  focused behavior evidence and does not claim a fresh upstream inventory unit.

## Non-Overlap

This does not repeat accepted attach/temp schema catalog shadowing,
attach/detach schema-cache generation invalidation, attach temp/VFS open
planning, attach/temp trigger FK resolution, JSON table sources/cursors, VFS
writer/sync/rollback apply, WAL checkpoint/savepoint byte truncation, B-tree
page movement/root collapse/overflow freelist release, SQL subquery/GROUP
BY/ORDER BY text execution, or Unicode GLOB behavior. The new behavior is the
current/next diff of cached table/index resolution winners across ATTACH/DETACH
shadow changes.

## Dependency Closure

No new support component is needed. The slice reuses lane-local
`SQLiteAttachedSchemaCatalog`, `SQLiteSchemaRecord`, and existing schema
resolution primitives; it does not require ext/sqlite, upstream binaries, live
services, or provider credentials.
