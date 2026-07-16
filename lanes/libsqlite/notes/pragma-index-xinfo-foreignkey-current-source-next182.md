# pragma-index-xinfo-foreignkey-current-source-next182

Adds a current-source PRAGMA page over the existing `index_xinfo` /
`foreign_key_list` parent-key diagnostics that also validates parent-key
collations. SQLite requires a foreign-key parent UNIQUE index to use the same
collating sequence declared on the parent table columns; a UNIQUE index with
the right column names but default `BINARY` collation must not unblock the
foreign-key repair path for a `TEXT COLLATE NOCASE` parent column.

This slice is narrower than accepted next176/next178 behavior. It reuses named
constraint, deferral timing, parent-key, and current-source pagination logic,
then adds only parent-table column `COLLATE` parsing, `PRAGMA index_xinfo`
collation comparison, mismatch counts, cursor invalidation, and row decoration.

Application use: copied `wp_options` imports can block resume when a generated
or migration-created lookup index has the correct parent key columns but was
created without the parent column collation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next182.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next182.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The implementation
reuses the lane-local schema catalog, `PRAGMA index_xinfo`,
`PRAGMA foreign_key_list`, CREATE TABLE parsing helpers, and current-source
cursor primitives.
