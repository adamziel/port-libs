# libsqlite encoding/collation affinity LIKE current-source next249

## Scope

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Plan` for a WordPress `wp_options.option_name COLLATE RTRIM LIKE ? ESCAPE ?` cursor over mixed UTF-8, UTF-16LE, and UTF-16BE source rows.
- Behavior covered: `RTRIM` collation can admit padded keys into the range scan, while the LIKE residual remains byte/text significant for trailing spaces. Current/next source switching records candidate, matched, residual-rejected, encoded-byte, encoding, and residual-match invalidation deltas.
- WordPress smoke: `examples/wordpress-option-name-rtrim-like-current-source-next249.php`.

## Evidence

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Test.php`
- Focused result: `1 test files / 96 assertions / 0 failures`
- Example smoke command: `php lanes/libsqlite/examples/wordpress-option-name-rtrim-like-current-source-next249.php`
- Syntax check: `php -l` on changed PHP files.
- Diff hygiene: `git diff --check -- lanes/libsqlite`.

## Non-Overlap

- Avoids accepted next245 dangling ESCAPE LIKE residuals, next244 mixed UTF NOCASE LIKE source switching, Unicode GLOB ranges, UTF-16 malformed guards, SQL executor, JSON table, WAL, VFS, pager, and B-tree clusters.

## Dependency Closure

- No new support component needed. This reuses native mixed UTF source decoding, LIKE tokenization, RTRIM collation range checks, and current-source invalidation diagnostics.
