# source-neutral-src-cast-like-glob-defaults-dynamic-20260601T091358Z-0

Scope:
- Cleaned `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php` current-source next167 through next173 from option-shaped source defaults to generic setting-key terms.
- Updated directly coupled tests and application examples for next167, next168, next169, next171, next172, and next173.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext167173Test.php` to guard the cleaned method blocks from reintroducing legacy row names.

Red-first evidence:
- Before cleanup, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext167Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext168Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext169Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext171Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext172Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext173Test.php` reported `6 test files, 319 assertions, 102 failures` because the current source still expected legacy row keys while the current tests had already moved toward generic setting names.

Verification:
- `php -l` on all changed PHP files => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext167Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext168Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext169Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext171Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext172Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext173Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext167173Test.php` => `7 test files, 468 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 5 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php` => `1 test files, 1 assertions, 0 failures`
- Self-tests passed for `application-utf16-nocase-like-rtrim-current-source-next167.php`, `next168.php`, `next171.php`, `next172.php`, and `next173.php`.
- `git diff --check -- lanes/libsqlite` => passed.

Dependency closure:
- No new support component is needed. The slice reuses existing UTF-16 decode, LIKE/RTRIM/NOCASE comparison, current-source cursor invalidation, and application example harness behavior.

Exclusions / follow-up:
- This slice intentionally owns only next167-next173 in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`. Later variants beginning around next176 still contain older option-shaped defaults and should be cleaned in a separate bounded source-neutral slice.
- Root harness not run; this is an isolated source-neutral micro-slice.
