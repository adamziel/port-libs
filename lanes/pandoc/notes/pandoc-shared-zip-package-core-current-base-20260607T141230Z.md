# pandoc-shared-zip-package-core-current-base-20260607T141230Z

Base accepted HEAD: `9fa2532d1407cdfcf7979d602b49aba1b4031366`

## Behavior

Native `ZipPackage` now rejects archives where the central directory uses an
Info-ZIP Unicode path extra field to expose a decoded package path, but the
corresponding local file header omits matching Unicode path source metadata.
This keeps DOCX/ODT/EPUB media handoff from importing bytes under a
central-directory-only renamed path before local-header provenance has been
validated.

The safe paired central/local Unicode path case remains readable, and directly
coupled safe raw-name tests were updated to carry the same Unicode path extra
field in the local header.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1030 assertions, 1 failures`

Failure: `Expected exception RuntimeException was not thrown` for the new
central-only Unicode path metadata case.

Final focused run after implementation:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1030 assertions, 0 failures`

Focused delta: `+1` PHP PASS case, `+6` focused assertions.

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`. The normal example
summary now reports `zipCentralUnicodePathMissingLocalPolicy=rejected`.

## Status Delta

- `lane-status.json` `phpPass`: `1512 -> 1513`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1932 -> 1933`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 167`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`ZipPackage` central/local header parser, Info-ZIP Unicode path CRC validation,
strict package preflight, focused ZIP tests, and the existing WordPress ZIP
package preflight example.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive,
external archive tool, online service, live provider test, or live-service
provider test was executed.

## Non-overlap

This does not repeat the accepted ZIP central-directory signature,
trailing-deflate, invalid DOS timestamp, Unicode-normalized name collision,
ZIP64 extra-field rejection, Unix symlink/special-file, or drive-letter path
preflight slices. It is narrowly scoped to central/local Unicode path source
metadata consistency.

## Follow-up

Next ZIP/OPC package work should stay bounded to non-overlapping native package
preflight such as ZIP64 locator/accounting diagnostics, additional central/local
metadata provenance needed by DOCX/ODT/EPUB media handoff, or OPC relationship
package integration.
