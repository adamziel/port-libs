# source-neutral-src-encoding-default-sources-dynamic-20260601T174300Z-0

## Scope

Owned the stale static and dynamic encoding affinity LIKE/GLOB callers around
`SQLiteEncodingAffinityLikeCurrentSourceNextPlan`:

- `SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`
- `application-option-value-affinity-like-current-next94.php`
- `application-utf16-affinity-glob-current-source-next105.php`

## Red-first evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`
  failed before the cleanup with `1 test files, 69 assertions, 12 failures`
  because legacy `option_id` rows fell back to array-position rowids in the
  neutral `keyValueRowValuePlan()` API.
- `php lanes/libsqlite/examples/application-option-value-affinity-like-current-next94.php --self-test`
  failed with an undefined `optionRowValuePlan()` method.
- `php lanes/libsqlite/examples/application-utf16-affinity-glob-current-source-next105.php --self-test`
  failed with an undefined `optionRowValueDynamicLikeGlobPlan()` method.

## Source-neutral cleanup

- Replaced stale direct fixture keys `option_id`, `option_name`, and
  `option_value` with `setting_id`, `key_name`, and `key_value`.
- Replaced `wp_options` default source arguments with `app_settings`.
- Migrated the example callers to `keyValueRowValuePlan()` and
  `keyValueRowValueDynamicLikeGlobPlan()`.
- Replaced direct fixture strings such as `siteurl`, `blog_public`,
  `plugin_*`, and `theme_*` with neutral application setting names while
  preserving the same row ordering, text-affinity, UTF-16 byte, rowset delta,
  malformed UTF-8, and invalidation assertions.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingAffinityLikeCurrentSourceNext94Test.php`
  passed: `1 test files, 69 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-option-value-affinity-like-current-next94.php --self-test`
  passed.
- `php lanes/libsqlite/examples/application-utf16-affinity-glob-current-source-next105.php --self-test`
  passed.
- Full final verification is recorded in the worker handoff/final response.

## Status delta

Source-neutral cleanup only. No `phpPass`, mapped-coverage, or lane-status
counter movement is claimed.

## Dependency closure

No new support component is needed. The cleanup reuses the existing native
text-affinity, LIKE/GLOB range, UTF-16 encoding, and current-source
invalidation helpers.
