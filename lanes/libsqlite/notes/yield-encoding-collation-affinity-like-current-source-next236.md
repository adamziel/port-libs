# Encoding/Collation/Affinity LIKE Current Source Next236

## Slice

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Plan` for Application `wp_options.option_name` scans using SQLite `LIKE ... ESCAPE` semantics.
- Covers literal `%` and `_` wildcard escaping, trailing escape non-match behavior, multibyte escape characters, ASCII-only `NOCASE` matching, text-affinity coercion for numeric option names, and current-source cursor invalidation.
- Adds `application-option-name-escaped-like-current-source-next236.php` as a local Application smoke/example.

## Non-Overlap

This next236 slice avoids accepted Unicode GLOB range work, the next232 malformed-byte `option_value` LIKE cluster, UTF-16 malformed insert guards, UTF-16 NOCASE/RTRIM LIKE cursor fences, and all VFS/WAL/B-tree/JSON/SQL executor surfaces.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization, SQLite text-affinity coercion, ASCII-only NOCASE folding, and current-source invalidation diagnostics.

## Verification

Local focused verification:

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Plan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-option-name-escaped-like-current-source-next236.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Test.php` -> `1 test files, 74 assertions, 0 failures`, 68 PASS lines.
- `php lanes/libsqlite/examples/application-option-name-escaped-like-current-source-next236.php` -> emits escaped LIKE current/next rowids, entered rowids, changed name bytes, and invalidation reasons.
- `php -r "json_decode(file_get_contents('lanes/libsqlite/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> passed.
