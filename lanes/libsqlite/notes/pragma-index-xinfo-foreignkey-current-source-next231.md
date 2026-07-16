# PRAGMA index_xinfo foreign key current-source next231

## Behavior

This slice adds a current-source PRAGMA/FK diagnostic for expression-based
UNIQUE parent indexes. SQLite exposes expression index terms in
`PRAGMA index_xinfo` with `cid = -2` and `name = NULL`; those terms cannot
satisfy a `FOREIGN KEY ... REFERENCES parent(column)` parent-key requirement.

The new `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` page composes
the accepted next229 exact parent-key arity page and appends
`foreign_key_parent_expression_unique` rows. It reports current copied
Application taxonomy-import schemas where only `lower(slug)` / `lower(slug),
taxonomy` UNIQUE expression indexes exist, and verifies that a next source
with plain `UNIQUE(slug)` / `UNIQUE(slug, taxonomy)` clears the blocker.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `62 PASS lines`
  - `1 test files, 81 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next231.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next231 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next231.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next231.php`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This avoids accepted next181 parent collation checks, next188 partial UNIQUE
parent keys, next218 RESTRICT timing, next227 child-index leftmost-prefix
checks, next228 DESC sort-order compatibility, next229 exact UNIQUE arity,
and batch201 next228-next230 PRAGMA/FK current-source surfaces. The new
behavior is specifically that expression UNIQUE indexes observed through
`PRAGMA index_xinfo` remain unusable as FK parent keys until a plain UNIQUE
parent-key index is present.

## Dependency Closure

No new support component is needed. The slice reuses lane-local schema
records, `SQLitePragmaSchemaCatalog::indexXInfo()`, and existing
`foreign_key_list` parsing.
