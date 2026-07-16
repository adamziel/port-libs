# ZIP General-Purpose Flag Preflight Slice

Slice: `pandoc-shared-zip-package-core-current-base-20260606T212209Z`
Base accepted HEAD: `9e6fd7a643da41e2535da077c68b60f0a50014b8`

## Behavior

- Added native `ZipPackage::generalPurposeFlagPreflight()` for bounded ZIP package imports.
- Records supported general-purpose metadata for UTF-8 names, data descriptors, and deflate option flags.
- Strict package handoff now rejects data-descriptor entries and deflate option flag entries with explicit diagnostics before Office/EPUB/ODT media import.
- Added `assertNoStrictGeneralPurposeFlagReviewEntries()` for callers that need a direct package-policy assertion.
- The WordPress ZIP preflight example now includes a parseable deflated data-descriptor package that remains readable but is rejected by strict handoff policy.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 911 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 911 assertions, 1 failures` because `ZipPackage::generalPurposeFlagPreflight()` was missing.
- Final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` -> `1 test files, 944 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` -> `zip package writer preflight self-test passed`.
- PHP lint passed for `lanes/pandoc/src/ZipPackage.php`, `lanes/pandoc/tests/ZipPackageTest.php`, and `lanes/pandoc/examples/wordpress-zip-package-preflight.php`.

Focused delta: +1 PHP PASS case, +33 focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP ZIP package parsing, local/central header validation, data descriptor parsing, raw deflate decoding, strict import preflight, and the existing WordPress ZIP package preflight example.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, external archive tool, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat prior ZIP central-directory signature, Unicode name collision, invalid DOS timestamp, trailing-deflate, ZIP64, symlink, NTFS timestamp, or unsupported compression-method slices. It covers the remaining supported general-purpose flag metadata before strict package handoff.

## Next

Continue ZIP package closure with non-overlapping package semantics such as ZIP64 extra-field size upgrade planning, local/central flag provenance reporting, or Office/EPUB/ODT-specific package policy integration. Keep external ZIP tools and office converters out unless explicitly authorized.
