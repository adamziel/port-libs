# PRAGMA index_xinfo / foreign-key current-source next212

This slice adds a non-overlapping current-source PRAGMA diagnostic for foreign-key child action lookups. It detects `ON DELETE` / `ON UPDATE` constraints whose child-column prefix is covered only by a partial child index, then records the repaired next-source state when a non-partial child index is present.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next212.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next212.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted parent-key expression/partial/collation/sort diagnostics, child-prefix collation/order quality, implicit parent primary-key arity, JSON table, WAL/VFS, and B-tree current-source clusters.
- Uses existing `SQLitePragmaSchemaCatalog` support only; no new support component is needed.
