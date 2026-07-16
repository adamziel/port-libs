# source-neutral-src-cast-like-glob-defaults-dynamic-20260601T110202Z-0

Slice: `source-neutral-src-cast-like-glob-defaults-dynamic-20260601T110202Z-0`

Base accepted HEAD: `7d7f56f4215c6f13694b7f52e7b7a9694f03a6ae`

## Scope

- Neutralized `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` current-source
  next183 through next189 row defaults from legacy option row/key names to
  generic `setting_id`, `key_name_bytes`, `key_name`, and `app_settings`.
- Updated the directly coupled next183, next184, next185, next186, next187,
  next188, and next189 focused tests and examples to the same generic
  application setting row contract.
- Fixed the stale next183 through next189 examples while migrating them to the
  current `keyValueRowKey*()` production API.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext183189Test.php` to
  guard the cleaned source block against reintroducing legacy row names.

## Red-First Evidence

- Before cleanup,
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext184Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext186Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext189Test.php`
  reported `7 test files, 156 assertions, 306 failures` because the direct
  tests still supplied legacy row keys to helpers that now require generic
  setting keys.

## Verification

- `php -l` changed PHP files: passed for the changed source, tests, guard, and
  examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext184Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext186Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext189Test.php`:
  `7 test files, 512 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext183Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext184Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext185Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext186Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext187Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext188Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext189Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext183189Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `10 test files, 519 assertions, 0 failures`.
- Changed examples with `--self-test`: next183, next184, next185, next186,
  next187, next188, and next189 all passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This is a source-neutral API/defaults
cleanup over the existing UTF-16 decoding, RTRIM expression-key, ASCII NOCASE,
LIKE wildcard, yielded-token replay, and current-source cursor helpers.

## Non-Overlap

This slice does not add upstream PASS rows, mapped denominator rows, or new
SQLite behavior. It follows the earlier next175-through-next181 cleanup and
only owns the adjacent next183-through-next189 UTF-16 NOCASE/RTRIM source
block plus direct tests/examples.
