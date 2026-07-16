# PRAGMA index_xinfo foreign-key current-source next351-358

Prepared the direct follow-on to merged next343-350 for action relationship diagnostics.

## Scope

- Added `page351()` through `page358()` on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Kept the slice limited to PRAGMA `index_xinfo` plus `foreign_key_list` action diagnostics.
- Covered SET NULL and SET DEFAULT child lookup indexes that are present but blocked by key-order or collation mismatches.

## Verification

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext351358Test.php
php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next351-358.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext351358Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next351-358.php --self-test
git diff --check
```

Expected self-test line:

```text
application-pragma-index-xinfo-foreignkey-current-source-next351-358 self-test passed
```
