# pandoc-zip-central-directory-review-fields-current-base-20260611T170059Z

Slice: `plib-hzwe8`, shared ZIP/OPC package primitives.
Base: current `origin/main` `b62560a6d`.

## Change

`ZipPackage::centralDirectoryVariableFieldsPreflight()` now preserves
central-directory review-field provenance for extra fields and entry comments:
total review bytes, review-bearing entry count, per-entry `reviewFieldBytes`,
and `largestReviewFieldEntry`.

The same summary is carried through raw strict and strict package preflights, so
OPC/DOCX/EPUB/ODF import handoffs can review central-directory metadata payloads
without exposing package part bytes or invoking external tools.

No Pandoc, office suites, zip/unzip, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  passed: 1 test file, 3409 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66216 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3125 -> 3126`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3213 -> 3214`.
- Added six focused `ZipPackageTest` assertions for central-directory review
  field provenance.
