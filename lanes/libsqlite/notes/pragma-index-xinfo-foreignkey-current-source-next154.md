# pragma-index-xinfo-foreignkey-current-source-next154

This slice adds a current/next source cursor for declared schema metadata:
`PRAGMA index_xinfo(...)` plus `PRAGMA foreign_key_list(...)`.

It is intentionally separate from accepted `foreign_key_check`,
quick_check/integrity rootpage, pointer-map, index-list enumeration, and
table-valued row-cursor surfaces. The new behavior detects schema-catalog drift
between current and next copied WordPress `wp_options` catalogs before resuming
an import diagnostic cursor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 75 assertions, 0 failures`
  - `70` PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next154.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next154 self-test passed`

Dependency closure: no new support component is needed. The slice reuses the
existing attached schema catalog, schema PRAGMA parser, table-valued PRAGMA
parser, `index_xinfo`, and `foreign_key_list` metadata extraction.

Non-overlap: avoids accepted next118 quoted `index_xinfo` + FK check behavior,
next138 quick_check/index/FK, next148 index-list/FK rootpage current/next
behavior, and batch147 PRAGMA foreign-key rootpage integrity. This slice only
compares declared `index_xinfo` and declared `foreign_key_list` catalog rows.
