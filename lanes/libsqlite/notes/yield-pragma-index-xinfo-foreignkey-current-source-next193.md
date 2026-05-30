# PRAGMA index_xinfo foreign-key parent order current-source next193

Slice: `pragma-index-xinfo-foreignkey-current-source-next193`.

## Behavior

Adds current/next PRAGMA catalog evidence for a SQLite foreign-key parent key
edge where a UNIQUE parent index contains the referenced columns as a set but
in the wrong order. SQLite cannot use that index to satisfy the parent key, so
`foreign_key_check` can remain blocked even though `PRAGMA index_xinfo` shows a
UNIQUE index over all referenced columns.

The new `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` layer builds on
accepted next189 catalog output and adds:

- `foreign_key_parent_unique_order` rows from `PRAGMA index_list` plus
  `PRAGMA index_xinfo`;
- source hashes and stale-cursor pagination that change when the parent UNIQUE
  order is repaired;
- decoration of missing parent-key rows with `column_order_mismatch`;
- guards so partial, expression, and subset UNIQUE indexes are not counted as
  this order-only blocker.

## Evidence

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
```

Result: `1 test files, 64 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next193.php --self-test
```

Result: `application-pragma-index-xinfo-foreignkey-current-source-next193 self-test passed`.

## Non-Overlap

Avoids accepted next181 parent collation checks, next188 partial UNIQUE parent
key handling, and next189 partial/expression UNIQUE rejection. This slice only
claims the wrong-column-order UNIQUE parent-key case.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and PRAGMA index/FK catalog
helpers.
