# PRAGMA index_xinfo foreign-key current-source next367-374

Prepared the direct follow-on to merged next359-366 for action relationship diagnostics.

## Scope

- Added `page367()` through `page374()` on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Kept the slice limited to PRAGMA `index_xinfo` plus `foreign_key_list` action diagnostics.
- Covered NO ACTION child lookup indexes blocked by key-order or collation mismatches.
- Covered CASCADE and RESTRICT child lookup indexes blocked by descending-key direction mismatches.

## Verification

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext367374Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next367-374.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext367374Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext359366Test.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext367374Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next367-374.php --self-test
git diff --check
```

Expected self-test line:

```text
application-pragma-index-xinfo-foreignkey-current-source-next367-374 self-test passed
```
