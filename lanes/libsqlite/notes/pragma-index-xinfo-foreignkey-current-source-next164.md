# PRAGMA index_xinfo / foreign_key current-source next164

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next PRAGMA helper for copied table arrays whose table or column keys differ from `sqlite_schema` only by identifier case.

- Reuses next161 catalog-derived `PRAGMA foreign_key_list` extraction, implicit parent primary-key resolution, parent affinity/collation enrichment, and next156 pagination/source validation.
- Canonicalizes table row arrays to schema table names case-insensitively.
- Adds schema column-name aliases from `PRAGMA table_xinfo` when imported rows use lower-case or upper-case column keys.
- Preserves original row keys while adding canonical aliases, so callers can keep their import payload shape.

## Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 68 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next164.php --self-test
wordpress-pragma-index-xinfo-foreignkey-current-source-next164 self-test passed
```

## Non-overlap

This avoids accepted next156 caller-supplied FK arrays, next159 catalog-derived explicit parent-column behavior, and next161 implicit parent primary-key resolution. The new behavior is case-insensitive schema/table/column row-array admission before existing `foreign_key_check` and `index_xinfo` current-source comparison.

It also avoids accepted PRAGMA optimize/index_xinfo, rootpage/integrity pagination, pointer-map, quickcheck, JSON, WAL, VFS, B-tree, and SELECT execution clusters.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog`, `PRAGMA table_xinfo`, `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, and the accepted next156 FK/index current-source cursor.
