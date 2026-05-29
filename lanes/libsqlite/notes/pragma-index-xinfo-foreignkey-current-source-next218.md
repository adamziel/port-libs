# PRAGMA index_xinfo / foreign-key current-source next218

This slice adds a current-source PRAGMA diagnostic for SQLite's `RESTRICT`
timing rule on deferrable foreign keys. SQLite still applies `ON DELETE
RESTRICT` and `ON UPDATE RESTRICT` immediately, even when the constraint is
`DEFERRABLE INITIALLY DEFERRED`; copied WordPress schema-repair tooling needs
that distinction before assuming all deferrable FK work can wait until commit.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next218.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next218.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Builds on accepted next212 child-action lookup rows without repeating partial child-index checks.
- Avoids accepted FK deferral summary next169 by adding the missing `RESTRICT` immediate-timing classification that combines schema deferral with `PRAGMA foreign_key_list` actions.
- No new support component is needed; this reuses the existing schema catalog, foreign-key list, and deferral parser helpers.
