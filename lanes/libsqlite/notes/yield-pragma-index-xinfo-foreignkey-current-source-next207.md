# PRAGMA index_xinfo / foreign_key current-source next207

- Added `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` for child-key index prefix diagnostics over `PRAGMA foreign_key_list` groups and `PRAGMA index_xinfo` rows.
- The slice is intentionally distinct from accepted parent-key admission, partial/expression parent rejection, rowid-alias parent keys, and `next206`: it reports whether child FK columns have a non-partial child-table index prefix for efficient FK checks/cascades.
- WordPress smoke: `wordpress-pragma-index-xinfo-foreignkey-current-source-next207.php` models copied `wp_option_import` staging rows gaining a composite child-key index before import/cascade validation.
- Dependency closure: no new support component is needed; this reuses `SQLitePragmaSchemaCatalog`, `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, and the accepted current-source pagination/resume shape.

Verification recorded by lane worker:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next207.php --self-test`
- `php -l` on changed PHP files
- `git diff --check -- lanes/libsqlite`
