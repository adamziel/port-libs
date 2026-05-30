# PRAGMA index_xinfo foreignkey current-source next279-286

Prepared the next279-286 follow-on slice for PRAGMA/FK parent-key diagnostics.

- `next279` reports `PRAGMA foreign_key_list` rows whose parent table is missing.
- `next280` reports parent columns missing from `PRAGMA table_info`.
- `next281` reports parent keys with no exact unique `PRAGMA index_xinfo` backing index.
- `next282` reports parent-key collation mismatches.
- `next283` reports partial unique parent keys.
- `next284` reports expression unique parent keys.
- `next285` reports implicit `rowid` parent-key references.
- `next286` reports composite parent key order mismatches.

Validation:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext279286Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next279-286.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext279286Test.php`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next279-286.php --self-test`
- `git diff --check`
