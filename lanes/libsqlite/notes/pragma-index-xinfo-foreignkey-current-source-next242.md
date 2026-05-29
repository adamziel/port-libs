# PRAGMA index_xinfo / foreign_key current-source next242

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA/FK diagnostic for explicit parent rowid aliases. SQLite exposes rowid
table payload entries through `PRAGMA index_xinfo` with `key = 0` and `cid =
-1`, but those auxiliary rows are not named parent-key columns. A foreign key
such as `REFERENCES parent(rowid)`, `REFERENCES parent(oid)`, or `REFERENCES
parent(_rowid_)` remains blocked unless the parent table declares a real column
with that name.

The new page composes the accepted next239 auxiliary-row page, appends
`foreign_key_parent_rowid_alias` rows, reports the rowid auxiliary index that
could be misread, and verifies that a next source using an explicit named parent
primary key clears the blockers.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `47 PASS lines`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next242.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next242 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next242.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next242.php`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This avoids accepted next217 parent-key prefix checks, next220 parent collation
checks, next234 expression parent-key rejection, next238 descending parent-key
admission, and next239 `index_xinfo` key=0 auxiliary-row admission. The new
behavior is specifically that explicit FK parent `rowid`/`oid`/`_rowid_`
aliases are rejected as parent keys even when a rowid auxiliary row appears in
`PRAGMA index_xinfo`, while declared columns with those names remain valid.

## Dependency Closure

No new support component is needed. The slice reuses lane-local schema records,
`SQLitePragmaSchemaCatalog::tableInfo()`, `indexXInfo()`, and existing
`foreign_key_list` parsing.
