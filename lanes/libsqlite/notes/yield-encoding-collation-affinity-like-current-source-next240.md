# encoding-collation-affinity-like-current-source-next240

- Slice: `encoding-collation-affinity-like-current-source-next240`.
- Behavior: adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Plan` for Application `wp_options.option_value` scans where `CAST(option_value AS NUMERIC) LIKE ?` compares integer, real, boolean, and text storage through SQLite-style text affinity while BLOB and NULL remain non-text.
- Current-source focus: retained rowids invalidate when the numeric-affinity formatted text changes, when the storage class changes even if the LIKE text remains the same, or when source/schema cookies or matched rowsets change.
- Application smoke: `examples/application-option-value-numeric-like-current-source-next240.php`.
- Non-overlap: avoids accepted next236 escaped `option_name` LIKE, UTF-16 NOCASE/RTRIM cursors, Unicode GLOB ranges, malformed text guards, and SQL/VFS/WAL/B-tree/JSON clusters.
- Dependency closure: no new support component needed; this reuses native LIKE tokenization and adds lane-local numeric/text-affinity formatting diagnostics.

Verification:

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Test.php`
- `php -l lanes/libsqlite/examples/application-option-value-numeric-like-current-source-next240.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Test.php`
- `php lanes/libsqlite/examples/application-option-value-numeric-like-current-source-next240.php --self-test`
- `git diff --check -- lanes/libsqlite`
