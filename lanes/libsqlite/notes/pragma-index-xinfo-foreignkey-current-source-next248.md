# PRAGMA index_xinfo / foreign_key current-source next248

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
diagnostic for foreign-key parent keys that are satisfied by an externally
created UNIQUE index. SQLite records `PRAGMA index_list` origin `c` for
`CREATE UNIQUE INDEX` and origin `u`/`pk` for inline UNIQUE/PRIMARY KEY
constraints. Parent keys backed only by origin `c` indexes are accepted while
the index exists, but dropping that index can leave a foreign-key mismatch.

The slice composes the accepted next245 generated-parent-key page, appends
`foreign_key_parent_external_unique` rows, and reports whether a next source
repairs the staging schema by moving the parent key to an inline UNIQUE
constraint.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `49 PASS lines`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next248.php`
  - `application-pragma-index-xinfo-foreignkey-current-source-next248 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next248.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next248.php`

## Non-Overlap

This avoids accepted next245 generated parent-key visibility, next244
expression parent-key rejection, next242 rowid alias rejection, next239
auxiliary `key=0` rows, next237/next229 parent-key arity, and next188/next217
partial/suffix parent unique-index blockers. The new behavior is specifically
`PRAGMA index_list` origin handling for FK parent keys that are otherwise valid
through `PRAGMA index_xinfo`.

## Dependency Closure

No new support component is needed. The slice reuses lane-local schema records,
`SQLitePragmaSchemaCatalog::indexList()`, `indexXInfo()`, and existing
`foreign_key_list` parsing.
