# PRAGMA index_xinfo + foreign_key current-source next157

## Behavior

- Added `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` to coordinate
  current/next `PRAGMA index_xinfo` metadata, integrity root diagnostics, and
  `foreign_key_check` rows under one stable resume cursor.
- The source id includes both current and next underlying source ids, so
  resume cursors are invalidated by database bytes, catalog rows, schema/FK
  rows, PRAGMA SQL, integrity SQL, table-valued mode, or FK catalog changes.
- Counts now expose key/auxiliary/expression/rowid index metadata, rootpage
  blockers, FK violation deltas, and `next_state.blocking` readiness.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 67 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next157.php
```

## Non-overlap

This avoids accepted next121 single-source index/FK yield, next137
index-list/FK integrity, next141 quickcheck/index/FK, next148 index_list plus
FK rootpage, and batch PRAGMA rootpage/quickcheck variants. The new behavior is
the paired current/next source cursor for `index_xinfo` rowsets plus
`foreign_key_check` readiness and stale-source rejection.

## Dependency Closure

No new support component is needed. This reuses native PRAGMA catalog
introspection, integrity root diagnostics, `foreign_key_check` collection, and
attached schema catalog hashing.
