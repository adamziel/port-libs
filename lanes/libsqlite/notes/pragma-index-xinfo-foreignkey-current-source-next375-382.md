# PRAGMA index_xinfo foreign-key current-source next375-382

Prepared the direct follow-on to merged next367-374 for descending child lookup index action diagnostics.

## Scope

- Added `page375()` through `page382()` on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Kept the slice limited to PRAGMA `index_xinfo` plus `foreign_key_list` action diagnostics.
- Covered SET NULL and SET DEFAULT child lookup indexes blocked by descending-key direction mismatches.
- Covered NO ACTION descending-key direction mismatches and mixed action clauses that expose the same blocker.

## Verification

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext375382Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next375-382.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext375382Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext367374Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext375382Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next375-382.php --self-test
git diff --check
```

Expected self-test line:

```text
wordpress-pragma-index-xinfo-foreignkey-current-source-next375-382 self-test passed
```
