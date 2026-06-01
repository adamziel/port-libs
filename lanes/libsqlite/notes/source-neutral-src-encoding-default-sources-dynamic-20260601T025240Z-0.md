# Source-neutral encoding dynamic row defaults

Slice: `source-neutral-src-encoding-default-sources-dynamic-20260601T025240Z-0`

Base accepted HEAD: `846bc1f5f2d625b546a4c52f04a021ba713d41de`

## Changes

- Neutralized `SQLiteEncodingAffinityLikeCurrentSourceNextPlan` dynamic
  LIKE/GLOB row-id defaults from `option_id` to `setting_id`.
- Updated the directly coupled dynamic LIKE and dynamic GLOB focused tests to
  use `setting_id`, `key_name`, and `key_value` row fields while preserving the
  same match ordering, text-affinity, byte-encoding, and invalidation
  assertions.
- Updated the application dynamic LIKE self-test example to the same generic
  application setting row contract.
- Added `SQLiteEncodingAffinityLikeCurrentSourceNextPlan.php` to the existing
  encoding source-neutral defaults guard.

## Verification

- `php -l lanes/libsqlite/src/SQLiteEncodingAffinityLikeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingAffinityDynamicLikeCurrentSourceNext99Test.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingUtf16AffinityLikeGlobCurrentSourceNext105Test.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
- `php -l lanes/libsqlite/examples/application-encoding-affinity-dynamic-like-current-source-next99.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingAffinityDynamicLikeCurrentSourceNext99Test.php lanes/libsqlite/tests/SQLiteEncodingUtf16AffinityLikeGlobCurrentSourceNext105Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 130 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-encoding-affinity-dynamic-like-current-source-next99.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This cleanup preserves the existing native
PHP text-affinity, LIKE/GLOB, UTF-16 encoding, and current-source invalidation
helpers while removing another domain-shaped row default from production source.

## Follow-up

Continue source-neutral cleanup across the remaining older encoding/current
source helpers that still expose historical `option_*` fixture names.
