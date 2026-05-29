# PRAGMA index_xinfo foreign-key current-source next173

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`.
It extends the accepted action-aware `PRAGMA index_xinfo` + `foreign_key_list`
current-source plan with CREATE TABLE foreign-key deferral metadata. The plan
tracks `DEFERRABLE INITIALLY DEFERRED`, `DEFERRABLE INITIALLY IMMEDIATE`, and
`NOT DEFERRABLE` timing, includes the timing in cursor source identity, and
decorates both parent-index admission rows and `foreign_key_check` rows.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-deferral-current-source-next173.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-deferral-current-source-next173.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is required. The slice reuses
`SQLitePragmaSchemaCatalog`, `SQLitePragmaForeignKeyCheck`, and the accepted
next167 current-source plan, adding only bounded DDL clause parsing for FK
timing.

Non-overlap: avoids accepted next167 action metadata, next165 action summaries,
foreign-key list rows, index_xinfo rows, parent-index admission checks, and
foreign-key violation row production. The new behavior is limited to deferral
timing changes that SQLite's `foreign_key_list` rowset does not expose.
