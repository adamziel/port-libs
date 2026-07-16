# Source-Neutral UTF-16 NOCASE LIKE RTRIM Cleanup

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T144949Z-0`

Base accepted HEAD: `0af7c1558eab56b0c7f231815cf34222c9e56c0d`

Changed production source:

- `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`

Source-neutral cleanup:

- Neutralized the owned next209, next210, next211, next212, next213, and next215 source paths from option-shaped row keys and `rtrim(option_name)` expression text to generic `setting_id`, `key_name_bytes`, and `rtrim(key_name)` terms.
- Updated the directly coupled tests and application examples to use `app_settings`/`copied-app-settings` scenarios and generic key-value helper names while preserving the existing UTF-16 decode, NOCASE LIKE, RTRIM, escape, embedded-NUL, source-refresh, and resume-token behavior assertions.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext209215Test` to guard this owned method batch against legacy source terms and to exercise the generic row shape.

Red-first evidence:

- Before the source/test neutralization, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext210Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext211Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext213Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext215Test.php` failed with `6 test files, 152 assertions, 280 failures` because the direct tests still passed legacy row keys into neutral source helpers.

Verification:

- `php -l` passed for the changed source file, six changed tests, six changed examples, the new source-neutral guard test, and this note is non-PHP.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext210Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext211Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext212Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext213Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext215Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext209215Test.php` -> `7 test files, 468 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext167173Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext175181Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext183189Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext190196Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext200208Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext209215Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `7 test files, 15 assertions, 0 failures`.
- Updated examples passed: next209 `--self-test`, next210 `--self-test`, next211 JSON smoke, next212 `--self-test`, next213 `--self-test`, and next215 `--self-test`.

Status delta:

- No `lane-status.json`, `phpPass`, or mapped-coverage counter change is claimed. This source-neutral slice removes production source naming debt and preserves direct behavior coverage.

Dependency closure:

- No new support component is needed. This cleanup reuses the existing native UTF-16 decode, NOCASE LIKE prefix range, RTRIM expression-key, embedded-NUL, and current-source cursor diagnostics.

Root harness:

- Not run - isolated micro-slice.

Next:

- Later next217+ sections in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` still contain option-shaped row keys and expression text; keep the next cleanup batch bounded to those methods and their direct tests/examples.
