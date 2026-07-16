# encoding-collation-affinity-like-current-source-next241

## Behavior

Adds `SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Plan`, a byte-aware
current-source cursor audit for `option_name COLLATE NOCASE LIKE ? ESCAPE ?`
over copied Application `wp_options` rows.

The slice covers embedded-NUL option names and malformed UTF-8 byte strings as
SQLite LIKE text tokens, not C-string terminators. It records prefix range
candidates, residual LIKE matches, malformed byte rows, text-affinity storage,
entered/exited rows, byte changes, and cursor invalidation reasons between
current and next sources.

## Verification

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Test.php`
- `php -l lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next241.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Test.php`
  - `1 test files, 74 assertions, 0 failures`
  - `66` PASS lines
- `php lanes/libsqlite/examples/application-encoding-collation-affinity-like-current-source-next241.php`

## Non-Overlap

This avoids accepted escaped wildcard next236, dynamic option-value LIKE next238,
Unicode GLOB ranges, UTF-16 malformed guards, UTF-16 NOCASE/RTRIM cursor fences,
SQL executor, VFS, WAL, B-tree, and JSON clusters.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization,
text affinity coercion, byte fallback for malformed UTF-8, and current-source
invalidation diagnostics.
