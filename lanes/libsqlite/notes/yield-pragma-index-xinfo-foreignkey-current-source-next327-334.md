# PRAGMA index_xinfo foreign-key current-source next327-334

Prepared the follow-on current-source slices after next323-326 for FK action diagnostics where the child lookup index is visible to `PRAGMA index_xinfo` but cannot be admitted as a complete child-key lookup.

- `next327`: update CASCADE with a partial child lookup index.
- `next328`: delete CASCADE with a partial child lookup index.
- `next329`: update CASCADE with an expression child lookup index.
- `next330`: delete CASCADE with an expression child lookup index.
- `next331`: update RESTRICT with an expression child lookup index.
- `next332`: delete RESTRICT with an expression child lookup index.
- `next333`: update NO ACTION with an expression child lookup index.
- `next334`: delete NO ACTION with an expression child lookup index.

The implementation extends `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::actionRelationshipDiagnosticRows311()` and reuses the existing `actionRelationshipDiagnosticPage311()` pagination, cursor, source-id, count, delta, and dependency surface.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext327334Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next327-334.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext327334Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next327-334.php --self-test`
- `git diff --check`
