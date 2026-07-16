# Source-Neutral UTF-16 CAST/GLOB Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260530T165147Z-0`

Accepted base: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`

## Changes

- Neutralized `SQLiteUtf16CastGlobCurrentSourceNextPlan` row API expectations from legacy option-shaped keys to `setting_id`, `key_name`, and `key_value_bytes`.
- Updated the directly coupled focused test and generic application example to use application settings/module data instead of historical option/plugin fixture terms.
- Added `SQLiteUtf16CastGlobCurrentSourceNextPlan.php` to the existing `SQLiteEncodingSourceNeutralDefaultsTest.php` source-neutral defaults guard.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUtf16CastGlobCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16CastGlobCurrentSourceNext135Test.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
- `php -l lanes/libsqlite/examples/application-utf16-cast-glob-current-source-next135.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16CastGlobCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  - `2 test files, 86 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-cast-glob-current-source-next135.php --self-test`
- `git diff --check -- lanes/libsqlite`

The prompted API guard file is not present in this worktree, so it could not be run.

## Dependency Closure

No new support component is needed. This preserves the existing PHP UTF-16 decode, CAST, and GLOB behavior while removing domain-shaped source defaults from the owned production surface.
