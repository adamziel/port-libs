# PRAGMA index_xinfo / foreign_key current-source next245

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA/FK diagnostic for parent keys that reference generated columns. SQLite's
`PRAGMA table_info` omits generated columns, while `PRAGMA table_xinfo` exposes
them with non-zero `hidden` codes. When `PRAGMA foreign_key_list` names one of
those generated parent columns, the port must combine `table_xinfo` visibility
with `PRAGMA index_xinfo` UNIQUE-index metadata instead of treating the parent
key as missing.

The new page composes the accepted next242 rowid-alias page, appends
`foreign_key_parent_generated_key` rows, reports stored/virtual hidden codes,
and verifies that a next source using ordinary visible parent columns clears the
current-source blockers.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `46 PASS lines`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next245.php`
  - `application-pragma-index-xinfo-foreignkey-current-source-next245 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next245.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next245.php`

## Non-Overlap

This avoids accepted next234 expression parent-key rejection, next239
`index_xinfo` key=0 auxiliary-row admission, next240/next241 implicit parent
primary-key resolution, and next242 rowid/oid alias rejection. The new behavior
is specifically generated parent-key visibility through `PRAGMA table_xinfo`
hidden columns plus `PRAGMA index_xinfo` UNIQUE key rows.

## Dependency Closure

No new support component is needed. The slice reuses lane-local schema records,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, `indexXInfo()`, and existing
`foreign_key_list` parsing.
