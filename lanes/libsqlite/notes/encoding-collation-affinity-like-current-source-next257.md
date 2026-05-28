# Encoding collation affinity LIKE current-source next257

## Behavior

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Plan`.
- Covers a WordPress `wp_options.option_name COLLATE NOCASE LIKE ?` cursor where current/next source rows change between UTF text, integer, and real storage. SQLite applies TEXT affinity before LIKE matching, so numeric option names can enter or leave the `2024%` prefix cursor while BLOB and NULL rows remain outside the LIKE result.
- Tracks current/next candidate rowids, residual matches, storage/encoding byte changes, ASCII-only NOCASE keys, malformed UTF-16 rows, and cursor invalidation reasons.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Test.php`
- `php lanes/libsqlite/examples/wordpress-option-name-numeric-like-current-source-next257.php --self-test`
- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Test.php`
- `php -l lanes/libsqlite/examples/wordpress-option-name-numeric-like-current-source-next257.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap and dependency closure

This slice avoids accepted next253 option_value TEXT-affinity LIKE, next245 dangling ESCAPE residuals, Unicode GLOB ranges, malformed UTF-16 insert guards, SQL/VFS/WAL/B-tree/JSON clusters, and suite evidence work. No new support component is needed; it reuses native LIKE prefix planning, TEXT affinity coercion, UTF-16 decode, and current-source invalidation diagnostics.
