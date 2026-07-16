# source-neutral-src-encoding-default-sources-dynamic-20260601T102339Z-0

Slice: `source-neutral-src-encoding-default-sources-dynamic-20260601T102339Z-0`

Base accepted HEAD: `7bd413e4c22aac9f2c5a76765dae0d142cb048cb`

## Scope

- Neutralized `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` current-source
  next175 through next181 row defaults from `option_id` / `option_name_bytes`
  / `option_name` to generic `setting_id` / `key_name_bytes` / `key_name`.
- Updated the directly coupled next175, next176, next177, next178, next180,
  and next181 focused tests and examples to the same generic application
  setting row contract.
- Fixed the stale next175 example method call while migrating it to the
  current `keyValueRowKeyTokenFingerprintPlan()` API.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext175181Test.php` to
  guard the cleaned source block against reintroducing legacy row names.

## Red-First Evidence

- Before cleanup,
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Test.php`
  failed with 150 failures because next175, next178, and next181 still supplied
  legacy row keys to source helpers that now require generic setting keys.
- Before cleanup,
  `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-token-current-source-next175.php --self-test`
  failed on the removed legacy `optionRowNameTokenFingerprintPlan()` method.

## Verification

- `php -l` changed PHP files: passed for the changed source, test, and example
  PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Test.php`:
  `6 test files, 434 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext175Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext176Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext177Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext178Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext180Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext181Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext175181Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `9 test files, 441 assertions, 0 failures`.
- Changed examples with `--self-test`: next175, next176, next177, next178,
  next180, and next181 all passed.
- `git diff --check -- lanes/libsqlite`: passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`:
  passed.

## Dependency Closure

No new support component is needed. This is a source-neutral API/defaults
cleanup over the existing UTF-16 decoding, RTRIM expression-key, ASCII NOCASE,
LIKE wildcard, and current-source replay helpers.

## Non-Overlap

This slice does not add upstream PASS rows, mapped denominator rows, or new
SQLite behavior. It follows the earlier next167-through-next173 cleanup and
only owns the adjacent next175-through-next181 UTF-16 NOCASE/RTRIM source
block plus direct tests/examples.
