# Source-Neutral Option Table Defaults Dynamic

Slice: `source-neutral-src-option-table-defaults-dynamic-20260601T151734Z-0`
Base accepted HEAD: `47ce92cb06fe604b95309ac683d21ead062958bb`

## Change

- Neutralized the remaining UTF-16 NOCASE/RTRIM current-source next217 through
  next233 production defaults in `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`.
- Replaced source-level `option_id` / `option_name_bytes` row expectations with
  `setting_id` / `key_name_bytes` and changed diagnostic expression strings from
  `rtrim(option_name)` to `rtrim(key_name)`.
- Updated the directly coupled next217 through next233 tests and examples to use
  the same generic row shape.
- Extended `SQLiteSourceNeutralOptionTableDefaultsDynamicTest` with a source
  segment guard for the neutralized next217 through next233 methods.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext217Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext218Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext219Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext221Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext223Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext224Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext225Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext226Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext227Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext228Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext229Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext231Test.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `16 test files, 1129 assertions, 0 failures`.
- Updated example self-tests passed for `application-utf16-nocase-like-rtrim-current-source-next217.php`, next218, next219, next221, next223, next224, next225, next226, next227, next228, next229, next230, and next231.
- `php -l` passed for all changed PHP files.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'` - passed.
- `git diff --check -- lanes/libsqlite` - passed.

## Dependency Closure

No new support component is needed. This cleanup reuses the existing native
UTF-16 decoding, LIKE prefix planning, RTRIM/NOCASE expression-key, prepared
pattern, byte-signature, Unicode-character, whitespace-boundary, and
current-source invalidation helpers.

## Non-Overlap

This is source-neutral production cleanup only. It does not add upstream PASS
rows, runner metadata, dashboard/root edits, or new compatibility aliases. The
remaining production-source legacy matches are SQLite compile-option helper names
and the older row-value returning window source, which is outside this bounded
UTF-16 option-table-defaults batch.

Root harness: not run - isolated micro-slice.
