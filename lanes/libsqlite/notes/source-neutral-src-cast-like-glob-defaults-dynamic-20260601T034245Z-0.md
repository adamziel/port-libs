# Source-Neutral UTF-16 LIKE/GLOB Current-Source Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T034245Z-0`

Base accepted HEAD: `6a9d70d6e954052f2443a5cdc428898114c4a14e`

Changed production source:

- `SQLiteUtf16GlobRangeCurrentSourceNextPlan`
- `SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan`
- `SQLiteUtf16RtrimGlobAffinityCurrentSourceNextPlan`
- `SQLiteUtf16RtrimGlobCurrentSourceNextPlan`
- `SQLiteUtf16RtrimNocaseCurrentSourceNextPlan`

Source-neutral cleanup:

- Replaced legacy production row defaults and diagnostics from `option_id`, `option_name*`, `option_value*`, `autoload`, and `wp_options` shaped terms to `setting_id`, `key_name*`, `key_value*`, `load_policy`, and `app_settings`.
- Updated direct UTF-16 LIKE/GLOB/RTRIM/NOCASE tests and examples to use the same generic row shape while preserving cursor invalidation, malformed text, range-bound, collation, affinity, and reprepare assertions.
- Extended `SQLiteEncodingSourceNeutralDefaultsTest` to guard the neutralized source files.

Verification:

- `php -l` passed for the five changed source files, seven changed tests, and six changed examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16GlobRangeCurrentSourceNext102Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext103Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext132Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimGlobCurrentSourceNext125Test.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobAffinityCurrentSourceNext92Test.php lanes/libsqlite/tests/SQLiteUtf16RtrimGlobAffinityCurrentSourceNext145Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `8 test files, 422 assertions, 0 failures`.
- Updated examples passed with `--self-test`.

Dependency closure:

- No new support component is needed. This cleanup reuses the existing native UTF-16 decoding, LIKE/GLOB range planning, RTRIM/NOCASE collation, affinity conversion, and current-source invalidation helpers.

Next:

- Continue neutralizing the remaining older UTF-16 NOCASE/RTRIM source files that still use option-shaped row fixtures and expression text.
