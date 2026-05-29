# PRAGMA index_xinfo / foreign-key current-source next224

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA helper layered on accepted `index_xinfo`, `foreign_key_list`, child
action lookup, and parent-key prefix behavior.

New behavior:

- derives foreign-key parent columns from `PRAGMA foreign_key_list`;
- reads parent column declared collations from `CREATE TABLE`;
- compares those collations with leading UNIQUE parent-index terms from
  `PRAGMA index_xinfo`;
- flags parent UNIQUE indexes whose key columns match but whose collations do
  not match SQLite's foreign-key parent-key requirement;
- reports current/next blocker counts, source summaries, pagination, and
  repair deltas for copied WordPress multisite option import schemas.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next224.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next224.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted next217 parent-key prefix/suffix
validation, next212 child action lookup, next211 child nullability, next209
implicit parent primary-key arity, or the accepted next218 timing/status
coverage. The new surface is parent-key collation matching across
`foreign_key_list` and `index_xinfo`.

Dependency closure: no new support component is needed. The slice reuses the
schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and the
existing current-source pagination chain.
