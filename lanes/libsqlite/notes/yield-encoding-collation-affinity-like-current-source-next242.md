# Encoding collation affinity LIKE current-source next242

Slice: `encoding-collation-affinity-like-current-source-next242`.

Behavior:
- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Plan` for `CAST(option_value AS TEXT) COLLATE NOCASE LIKE ? ESCAPE ?` where the literal prefix contains an embedded NUL byte.
- Models SQLite LIKE tokenization where `\0` is an ordinary text character, escaped `_` stays literal, `%` runs after the NUL-bearing prefix, and NOCASE folds ASCII only around the NUL byte.
- Adds a Application-oriented smoke for copied `wp_options` rows whose option values include NUL-bearing plugin cache keys.

Non-overlap:
- Avoids accepted next239 Unicode/malformed GLOB ranges, next236 escaped `option_name` LIKE, next237 option-value escaped wildcard LIKE, next238 REAL text-affinity LIKE, next235 malformed-byte NOT LIKE, UTF-16 malformed guards, and unrelated SQL/VFS/WAL/B-tree/JSON clusters.

Dependency closure:
- No new support component is needed; this reuses native LIKE tokenization, PHP embedded-NUL strings, text-affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics.

Focused verification:
- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Test.php`
- `php -l lanes/libsqlite/examples/application-embedded-nul-like-current-source-next242.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Test.php`
- `php lanes/libsqlite/examples/application-embedded-nul-like-current-source-next242.php`
- `git diff --check -- lanes/libsqlite`
