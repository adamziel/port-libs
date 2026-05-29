# PRAGMA index_xinfo / foreign_key_check current-source next185

## Behavior

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current/next
PRAGMA page wrapper that keeps accepted `index_xinfo` and foreign-key parent
admission behavior from next182, then adds explicit rows for SQLite's
`foreign_key_check` NULL-child-key exemption. A child row with any NULL child
key column is recorded as `foreign_key_null_child_key` with `status:
not_checked`, so copied WordPress option imports can distinguish real parent
violations from rows SQLite omits from `PRAGMA foreign_key_check`.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next185.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next185 self-test passed`

## Non-Overlap

This does not repeat accepted next181/next182 parent collation checks,
rootpage checks, recursive foreign-key catalog output, or foreign-key parent
unique-index admission. The new rows specifically cover `foreign_key_check`
omission of NULL child keys while preserving the existing current-source cursor
guard.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
schema-record catalog, PRAGMA `index_xinfo` handling, and
`SQLitePragmaForeignKeyCheck` semantics.
