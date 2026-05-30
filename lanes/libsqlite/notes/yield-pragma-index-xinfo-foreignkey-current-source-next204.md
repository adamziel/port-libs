# PRAGMA index_xinfo/foreign_key_list current-source next204

- Slice: child-side foreign-key index coverage for paged `PRAGMA index_xinfo` plus `PRAGMA foreign_key_list` diagnostics.
- Behavior: reports whether each child FK column group has a usable non-partial, non-expression child-side index prefix, preserving current/next source ids so copied Application termmeta imports can decide whether to build a lookup index before cascade/delete repair.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php` passed with `1 test files, 60 assertions, 0 failures` and 53 PASS lines.
- Example target: `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next204.php --self-test`.
- Non-overlap: avoids accepted next202 table-valued index/FK pagination, next203 parent UNIQUE coverage, prior parent collation/sort/partial/superset/expression diagnostics, and accepted PRAGMA integrity/FK pointer-map surfaces.
- Dependency closure: no new support component is needed; this reuses native `SQLitePragmaSchemaCatalog` index_list, index_xinfo, and foreign_key_list parsing.
