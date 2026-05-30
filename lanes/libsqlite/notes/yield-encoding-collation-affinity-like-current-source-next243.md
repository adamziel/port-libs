# Encoding/Collation/Affinity LIKE Current Source Next243

## Slice

- Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Plan` for Application `wp_options.option_value` scans where `LIKE` is evaluated against text-affinity values while an `RTRIM` collation key is available for candidate diagnostics.
- Covers the upstream SQLite distinction that `RTRIM` collation trims trailing spaces for collation keys, but the `LIKE` residual still sees the original text and does not turn exact `LIKE` into `RTRIM` equality.
- Adds `application-encoding-rtrim-like-current-source-next243.php` as a Application smoke for current/next option rows with trailing spaces, ASCII `NOCASE`, text affinity, blob/NULL unknown rows, and cursor invalidation reasons.

## Non-Overlap

This next243 slice avoids accepted Unicode GLOB range handling, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences, REAL text-affinity LIKE next238, escaped option-name LIKE next236, malformed-byte LIKE/NOT LIKE, and unrelated SQL/VFS/WAL/B-tree/JSON surfaces.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE planning, scalar text-affinity coercion, RTRIM key normalization, and current-source cursor invalidation diagnostics.

## Verification

Local focused verification:

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Plan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-encoding-rtrim-like-current-source-next243.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Test.php` -> `1 test files, 77 assertions, 0 failures`, 67 PASS lines.
- `php lanes/libsqlite/examples/application-encoding-rtrim-like-current-source-next243.php --self-test` -> `application-encoding-rtrim-like-current-source-next243 self-test passed`.
- `php -r "json_decode(file_get_contents('lanes/libsqlite/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"` -> `lane-status json ok`.
- `git diff --check -- lanes/libsqlite` -> passed.
