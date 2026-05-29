# PRAGMA index_xinfo foreign-key current-source next359-366

Prepared the direct follow-on to merged next351-358 for action relationship diagnostics.

## Scope

- Added `page359()` through `page366()` on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Kept the slice limited to PRAGMA `index_xinfo` plus `foreign_key_list` action diagnostics.
- Covered CASCADE and RESTRICT child lookup indexes that are present but blocked by key-order or collation mismatches.

## Verification

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext359366Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next359-366.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext359366Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next359-366.php --self-test
git diff --check
```

Expected self-test line:

```text
wordpress-pragma-index-xinfo-foreignkey-current-source-next359-366 self-test passed
```
