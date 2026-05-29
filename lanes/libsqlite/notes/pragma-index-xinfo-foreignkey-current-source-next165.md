# PRAGMA index_xinfo / foreign-key current-source next165

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next PRAGMA helper that keeps `PRAGMA foreign_key_list` action metadata attached to combined `index_xinfo`, parent-index admission, and `foreign_key_check` rows.

- Preserves `on_update`, `on_delete`, and `match` values from catalog-derived `pragma_foreign_key_list` rows.
- Adds per-row `action_summary` for FK parent-index admission and violation rows.
- Adds current/next source action summaries so copied WordPress imports can reject stale resume cursors when action-bearing FK catalog state changes.
- Reuses the existing next156 pagination/source validation and next161 catalog FK derivation.

## Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
71 PASS lines
1 test files, 78 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next165.php --self-test
wordpress-pragma-index-xinfo-foreignkey-current-source-next165 self-test passed
```

## Non-overlap

This does not repeat next156 caller-supplied FK arrays, next159/next161 implicit parent-column derivation, next162 implicit parent coverage, queued next163 implicit primary-key replay, or queued next164 casefolded row-array admission. The new surface is preserving `ON UPDATE`, `ON DELETE`, and `MATCH` metadata through the existing current-source PRAGMA page.

It also avoids accepted quickcheck/rootpage, PRAGMA optimize/index_xinfo analysis, pointer-map, WAL, VFS, JSON, B-tree, SELECT, and trigger clusters.

## Dependency Closure

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog::foreignKeyList`, `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, and the accepted next156 FK/index current-source cursor.
