# Attach Temp WAL Schema Cache Current Source Next96

## Behavior

This slice adds statement-level source-schema handling to
`SQLiteAttachWalTempSchemaCookieSourcePlan`. Prepared SQL coming from an
attached schema object can now carry `source`, so unqualified table names
resolve through SQLite-style current-source search order: `temp`, then the
statement source schema, then `main`, then later attached schemas. Existing
global-source behavior remains the default for statements that do not provide a
source.

The new focused test covers attached archive/network view SQL, temp schema DDL
shadowing, WAL page-one/current cookie sources, WAL commit-header next cookies,
qualified main references, write retry routing, active read snapshots, quoted
source normalization, and missing source rejection.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext96Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cache-current-source-next96.php
```

## Non-Overlap

This avoids accepted ATTACH WAL/temp rollback routing, view/trigger routing,
schema-cookie source next87, schema cache next88, JSON, pager, VFS, and WAL
reader/checkpoint clusters. The new behavior is the narrower unhandled
statement current-source resolution inside the existing schema-cache source
planner.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP
ATTACH/WAL schema-cookie planner and statement lifecycle parser.
