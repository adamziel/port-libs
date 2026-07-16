# encoding-collation-affinity-like-current-source-next245

## Behavior

Adds a focused current-source plan for SQLite `LIKE ... ESCAPE` patterns whose final pattern character is the escape character. The prefix range can still admit `wp_options.option_name` candidates such as `plugin_cache`, but the residual LIKE comparison is false until the escape is completed (for example `plugin!_cache!!`).

This is Application-relevant for copied option-name scans where import filters generate escaped literal `_` predicates and must not treat a dangling escape as a match.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Test.php`
- `php -l lanes/libsqlite/examples/application-dangling-escape-like-current-source-next245.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Test.php`
  - `1 test files, 93 assertions, 0 failures`
  - `79` PASS lines
- `php lanes/libsqlite/examples/application-dangling-escape-like-current-source-next245.php`
  - emitted `encoding-collation-affinity-like-current-source-next245`

## Non-Overlap

This avoids accepted next242 embedded-NUL `option_value` LIKE, next241 byte-aware `option_name` LIKE, Unicode GLOB range handling, UTF-16 malformed guards, VFS/WAL/B-tree/JSON clusters, and status-only suite evidence.

## Dependency Closure

No new support component is needed. The slice reuses native LIKE tokenization, ESCAPE prefix planning, text-affinity coercion, byte fallback for malformed text, and current-source invalidation diagnostics.
