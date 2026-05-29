# PRAGMA index_xinfo foreign-key current-source next343-350

Prepared the follow-on current-source pages after the merged next335-342 chain.

## Scope

- Added `page343()` through `page350()` on `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
- Kept the source limited to PRAGMA `index_xinfo` and foreign-key action relationship diagnostics.
- Covered SET NULL and SET DEFAULT action diagnostics where child lookup indexes are partial or expression indexes.

## Verification

```sh
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350.php --self-test
```

Expected self-test line:

```text
wordpress-pragma-index-xinfo-foreignkey-current-source-next343-350 self-test passed
```
