# PRAGMA index_xinfo foreign-key current-source next251

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251`, an additive
current-source PRAGMA/FK page over accepted next248. The new rows catch
child-side foreign-key action lookup indexes whose left prefix starts with the
FK child column but then includes an expression key term reported by
`PRAGMA index_xinfo` (`cid = -2` or `name = NULL`).

For cascading, SET NULL, SET DEFAULT, or RESTRICT actions, SQLite can use a
plain child-key index to find rows efficiently. An expression-key index such as
`CREATE INDEX ... ON child(option_name, lower(locale))` must not be counted as
covering the composite child key `(option_name, locale)`. The next-source
repair is a normal child index on the exact FK child columns.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext251Test.php`
  - `51 PASS lines`
  - `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next251.php`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next251 self-test passed`

## Non-Overlap

This avoids accepted next212 partial child action indexes, next232
misordered/leftmost child action prefixes, next205 child prefix
collation/order quality, next247 SET DEFAULT child defaults, next248 external
parent UNIQUE origin handling, next245/next246 generated parent columns, and
accepted PRAGMA integrity/FK pagination. The narrower surface is expression
key terms inside child-side action lookup indexes as exposed by
`PRAGMA index_xinfo`.

## Dependency Closure

No new support component is needed. The slice reuses the existing schema
catalog, `PRAGMA foreign_key_list` extraction, `PRAGMA index_list`, and
`PRAGMA index_xinfo` key-row metadata.
