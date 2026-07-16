# Consolidate Numbered Encoding Affinity LIKE Plans

## Scope

- Consolidates the numbered `SQLiteEncodingCollationAffinityLikeCurrentSourceNext*Plan` production family into `SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan`.
- Deletes the numbered production classes without compatibility shims.
- Migrates direct tests and Application examples to the canonical class.
- Keeps variant behavior self-contained in the canonical production class. The next250 RTRIM residual variant now uses the clearer entry method `applicationRtrimLikeResidualSourcePlan()` to avoid colliding with the older next243 `applicationRtrimLikeResidualPlan()` entry method.

## Verification

- `php -l lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan.php`
- `php -l` over the changed direct tests and examples passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext232Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext235Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext236Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext237Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext238Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext240Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext241Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext243Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext245Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext247Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext248Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext249Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext250Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext251Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext252Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext253Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext255Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext256Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext257Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext258Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext259Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext260Test.php lanes/libsqlite/tests/SQLiteEncodingCollationAffinityLikeCurrentSourceNext261Test.php`
  - `27 test files, 2175 assertions, 0 failures`
- Changed Application examples executed with `php` and exited successfully.
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

## Dependency Closure

No new support component is needed. This is a production class consolidation only; all existing LIKE, collation, encoding, and affinity helper dependencies remain under `lanes/libsqlite/src`.
