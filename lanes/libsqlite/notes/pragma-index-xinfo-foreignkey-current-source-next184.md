# pragma-index-xinfo-foreignkey-current-source-next184

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a
current-source PRAGMA helper layered on the accepted `index_xinfo` and
foreign-key catalog path. It records parent-key sort order from
`PRAGMA index_xinfo.desc` for every catalog-derived FK parent key row.

Behavior covered:

- current/next source hashes include FK parent-index ASC/DESC metadata;
- paged row streams append `foreign_key_parent_sort` rows after the accepted
  index_xinfo, FK, timing, constraint, key, and collation rows;
- rowid parent keys report ASC order, DESC parent UNIQUE index terms are
  counted, and missing parent keys remain unmapped;
- stale cursor rejection catches changed parent-index DESC metadata;
- table-valued `pragma_index_xinfo(...)` sources preserve the same sort rows.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next184.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next184.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next184.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next184 self-test passed`

Non-overlap: this avoids accepted next181 parent collation admission, next178
parent key column mapping, next177/176 constraint metadata, next175
`foreign_key_list` row ordering, earlier FK admission/index_xinfo checks, and
the accepted PRAGMA optimize/index_xinfo/table-info clusters. The new surface
is `PRAGMA index_xinfo.desc` parent-key sort-order metadata in the combined FK
current-source cursor.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `index_xinfo`, `foreign_key_list`, parent-key mapping,
collation, and current-source pagination helpers.
