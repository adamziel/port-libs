# PRAGMA index_xinfo / foreign_key current-source next239

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, which reports
`PRAGMA index_xinfo` `key=0` auxiliary rows on UNIQUE parent indexes used by
foreign keys. SQLite exposes rowid-table auxiliary rowid entries and WITHOUT
ROWID primary-key tail entries in `index_xinfo`, but those rows are storage
payload rather than parent-key arity. The current-source page records them as
ignored so copied Application schema import checks do not reject a valid parent key
as a wider UNIQUE index.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next239.php --self-test`
- PHP lint for changed PHP files
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; the slice reuses
`SQLitePragmaSchemaCatalog::indexXInfo()` and existing schema-record parsing.
