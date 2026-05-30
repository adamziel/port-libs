# Source-neutral CAST/LIKE/GLOB BLOB scan cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260530T171152Z-0`

Accepted base: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`

Changed source surface:

- `SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan` now consumes generic `setting_id`, `key_name`, `key_value`, and `load_policy` row fields.
- Trace output now reports `keyName` and `loadPolicy` instead of legacy option/load names.
- Diagnostics for required row keys now use `setting_id` and `key_value`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php`
  - `1 test files, 90 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteBlobLikeGlobAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBlobLikeGlobAffinityCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-blob-like-glob-affinity-current-source-next234.php`
- `php lanes/libsqlite/examples/application-blob-like-glob-affinity-current-source-next234.php --self-test`

Dependency closure:

No new support component needed. The cleanup reuses existing scalar storage classification, `SQLiteBlobValue` bytes, LIKE/GLOB residual matching, and current-source invalidation diagnostics.

Dashboard movement:

No `phpPass` or mapped coverage delta is claimed; this is a source-neutral production cleanup with preserved focused coverage.
