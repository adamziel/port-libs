# pragma-index-xinfo-foreignkey-current-source-next171

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source PRAGMA helper that builds on the accepted index_xinfo/foreign_key action cursor and derives foreign-key timing from SQLite schema DDL:

- `DEFERRABLE INITIALLY DEFERRED`
- `DEFERRABLE INITIALLY IMMEDIATE`
- `NOT DEFERRABLE` / omitted timing

The timing summary is included in the current/next source hash, per-side counts, decorated parent-index admission rows, and decorated `foreign_key_check` rows. This keeps copied WordPress `wp_options` import diagnostics from treating commit-deferred violations as statement-immediate blockers when the FK DDL changed between current and next sources.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next171.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next171.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the accepted `SQLitePragmaSchemaCatalog`, `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, and current-source cursor paging behavior.
