# Legacy DOC/CFB Absent Chain Header Sentinels - 2026-06-09

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260609T040656Z`

Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`

## Source Truth

- MS-CFB header preflight treats absent MiniFAT and DIFAT chains as absent only when their header start fields are `ENDOFCHAIN`.
- Reserved sector markers such as `FREESECT`, `FATSECT`, and `DIFSECT` are not valid stand-ins for an absent MiniFAT or DIFAT chain.

## Implementation

- Tightened native `CompoundFileBinary` header validation so zero MiniFAT sectors require a MiniFAT start sector of `ENDOFCHAIN`.
- Tightened native `CompoundFileBinary` header validation so zero DIFAT sectors require a DIFAT start sector of `ENDOFCHAIN`.
- Added focused legacy DOC/CFB coverage for `FREESECT` and reserved-sector start markers on absent MiniFAT/DIFAT chains.
- Extended the WordPress legacy DOC handoff self-test with a regular-only CFB fixture and the same absent-chain rejection checks before exposing `WordDocument` bytes.

## Verification

- Rework note scan: no current `port-pandoc-*.needs-lane-rework.md` note was present for this lane.
- Baseline focused test before source edit:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 2059 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 2063 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`
- Syntax checks:
  - `php -l lanes/pandoc/src/CompoundFileBinary.php`
  - `php -l lanes/pandoc/tests/LegacyDocReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-legacy-doc-handoff.php`
  - Result: no syntax errors detected
- JSON status/manifest validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both files decoded successfully
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output
- Root harness: not run - isolated micro-slice

## Status Delta

- `phpPass`: `2275` to `2276`
- `benchmarkDenominator.mapped`: `2677` to `2678`
- `legacyDocCfbCoreCases`: `7` to `8`
- `legacyDocCfbCoreAssertions`: `64` to `68`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CompoundFileBinary` parser, `LegacyDocReader` focused tests, and WordPress legacy DOC handoff example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external converter, TeX/PDF engine, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted legacy DOC/CFB MiniFAT cutoff, MiniFAT allocation, DIFAT overflow, root timestamp, directory hygiene, FIB Unicode, encrypted stream, field-code, property-set, route-slip, ObjectPool, macro, or inline picture placeholder slices. It only owns absent MiniFAT/DIFAT header-start sentinel validation.

## Follow-Up

Separate legacy DOC/CFB follow-up can target SummaryInformation codepage handling, Word table/list handoff, or another CFB allocation invariant not covered by absent MiniFAT/DIFAT starts.
