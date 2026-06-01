# source-neutral-src-encoding-default-sources-dynamic-20260601T120737Z-0

Slice: `source-neutral-src-encoding-default-sources-dynamic-20260601T120737Z-0`

Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Scope

- Neutralized `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` current-source
  next190 through next196 expression/default source names from legacy option
  row/key language to generic `setting_id`, `key_name_bytes`, `key_name`, and
  `app_settings`.
- Updated the directly coupled next190, next191, next192, next193, next194,
  next195, and next196 focused tests and examples to the same generic
  application setting row contract.
- Added `SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext190196Test.php` to
  guard the cleaned source block against reintroducing legacy row names.
- Left `lane-status.json` unchanged because this source-neutral cleanup does
  not add PASS rows, mapped denominator rows, or new SQLite behavior.

## Red-First Evidence

- Before cleanup,
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext190Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext191Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext192Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext193Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext194Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext195Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext196Test.php`
  reported `7 test files, 11 assertions, 440 failures` because the direct
  tests still supplied legacy row keys to helpers that now require generic
  setting keys.

## Verification

- `php -l` changed PHP files: passed for the changed source, tests, guard, and
  examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext190Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext191Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext192Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext193Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext194Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext195Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext196Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimSourceNeutralCurrentNext190196Test.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`:
  `10 test files, 554 assertions, 0 failures`.
- Changed examples with `--self-test`: next190, next191, next192, next193,
  next194, next195, and next196 all passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. This is a source-neutral API/defaults
cleanup over the existing UTF-16 decoding, RTRIM expression-key, ASCII NOCASE,
LIKE wildcard, yielded-token replay, duplicate-peer resume, and current-source
cursor helpers.

## Non-Overlap

This slice does not add upstream PASS rows, mapped denominator rows, or new
SQLite behavior. It follows the earlier next183-through-next189 cleanup and
only owns the adjacent next190-through-next196 UTF-16 NOCASE/RTRIM source
block plus direct tests/examples. Later variants in the large source file are
left for subsequent bounded source-neutral slices.
