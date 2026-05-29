# PRAGMA index_xinfo foreign-key current-source next181

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
wrapper over accepted next178 parent-key mapping. The new behavior keeps
`PRAGMA index_xinfo` parent key rows tied to the parent table column
collations, so a copied WordPress import can distinguish a valid UNIQUE parent
key from a same-column unique index whose collation sequence is not admissible
for SQLite foreign-key enforcement.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next181.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next181.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted next175 `foreign_key_list` column-sequence
rows, next178 parent-key column mapping, batch166 PRAGMA index_xinfo/foreign-key
behavior, and accepted quickcheck/rootpage/pointer-map PRAGMA integrity
surfaces. The new slice is FK parent UNIQUE-index collation admission beside
existing `index_xinfo` rows.

Dependency closure: no new support component is needed. The slice reuses the
existing schema record catalog, `PRAGMA index_xinfo`, FK parent-key rows, and
current-source cursor conventions.
