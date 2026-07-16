# Source-Neutral UTF-16 NOCASE LIKE/RTRIM Next200-208 Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T124443Z-0`

Base accepted HEAD: `687c594e4d06eca0127679aada46331adea32e3c`

Changed production source:

- `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`

Source-neutral cleanup:

- Neutralized the next200-next208 UTF-16 NOCASE LIKE/RTRIM row fixtures and diagnostics from `option_id` and `option_name*` terms to generic `setting_id` and `key_name*` terms.
- Updated the directly coupled current-source tests and application examples to assert the generic row shape while preserving the existing LIKE/GLOB range, RTRIM/NOCASE, malformed text, reprepare, cursor invalidation, and escape/BOM behavior assertions.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext200208Test` to guard the owned source blocks against reintroducing `wp_*`, `option_*`, `blog_id`, or `autoload` defaults.

Verification:

- `php -l` passed for the changed production source, eight changed tests, the new guard test, and eight changed examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext200Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext201Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext203Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext204Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext205Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext206Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext207Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext208Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext200208Test.php` -> `10 test files, 692 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext200208Test.php` -> `3 test files, 8 assertions, 0 failures`.
- Updated examples passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.
- Root harness not run; this was an isolated source-neutral micro-slice.

Dependency closure:

- No new support component is needed. The patch reuses the existing UTF-16 decoding, LIKE prefix/range planning, RTRIM/NOCASE comparison, malformed text, escape, BOM, and current-source invalidation helpers.

Non-overlap:

- This cleanup follows the earlier neutralization of adjacent `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` blocks and owns only next200-next208 plus their directly coupled tests/examples.
- No lane-status or progress counters were changed because this is production-source naming debt cleanup, not new behavior coverage.

Next:

- Continue neutralizing the remaining older cast/LIKE/GLOB source blocks, especially the separate `SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan` defaults that still contain option-shaped source text.
