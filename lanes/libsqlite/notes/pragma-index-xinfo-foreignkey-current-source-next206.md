# PRAGMA index_xinfo / foreign_key current-source next206

## Behavior

Adds a current/next PRAGMA catalog layer for foreign-key parent coverage through
an explicit `INTEGER PRIMARY KEY` rowid alias. SQLite accepts
`REFERENCES parent(integer_primary_key_column)` without a separate
`PRAGMA index_list` row, so the existing parent UNIQUE-index coverage can make
that FK look missing unless the table-info primary-key column is considered.

The slice is intentionally narrower than accepted next203 parent UNIQUE-index
coverage, next188 partial UNIQUE parent diagnostics, and next181/184 collation
and sort diagnostics.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next206.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 67 assertions, 0 failures`
  - `59` focused `PASS` lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next206.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next206 self-test passed`

## WordPress Relevance

Copied WordPress import tables often reference parent taxonomy or term tables
through integer IDs. This preflight keeps those FK parent references countable
as valid when the parent key is the rowid alias and no separate parent index
appears in `PRAGMA index_list`.

## Dependency Closure

No new support component is needed. The implementation reuses
`SQLitePragmaSchemaCatalog::tableInfo()`, `foreign_key_list`, and current
schema records.
