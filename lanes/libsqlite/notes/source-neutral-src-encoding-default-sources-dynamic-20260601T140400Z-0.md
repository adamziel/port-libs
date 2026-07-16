# source-neutral-src-encoding-default-sources-dynamic-20260601T140400Z-0

## Scope

Owned the encoding/default-source dynamic LIKE/GLOB surface in `SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php` plus directly coupled current-source tests and application examples.

## Source-neutral cleanup

- Replaced hardcoded/default setting source names from `wp_options` to `app_settings`.
- Renamed row and result surfaces from `option_id`, `option_name`, `option_value`, and `autoload` terms to `setting_id`, `key_name`, `key_value`, and `load_policy`.
- Renamed mixed-case internals such as `optionName`, `optionValue`, and `optionId` to `keyName`, `keyValue`, and `settingId`.
- Replaced direct fixture prefixes such as `wp_` / `WP_` with `app_` / `APP_` while preserving LIKE/GLOB, affinity, collation, malformed-byte, and current-source invalidation assertions.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest.php` to cover this encoding source file and the legacy mixed-case source terms.

## Verification

- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l` passed for all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `29 test files, 2182 assertions, 0 failures`.
- Changed encoding examples smoke passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.

## Status delta

Source-neutral cleanup only. No `phpPass` or mapped-coverage counter movement is claimed.

## Dependency closure

No new support component is needed. The cleanup reuses the existing LIKE/GLOB tokenizers, text-affinity coercion, UTF decoding, collation cursor, malformed-byte handling, and current-source invalidation helpers.
