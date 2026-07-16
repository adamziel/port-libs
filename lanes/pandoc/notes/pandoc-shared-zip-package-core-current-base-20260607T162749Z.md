# pandoc-shared-zip-package-core-current-base-20260607T162749Z

Base accepted HEAD: `1d69a68f53ce21789449f52c6103c11f01fcd7a9`

## Behavior

This slice adds bounded native PHP ZIP archive-extra-data record policy for
DOCX/ODT/EPUB-style package imports. `ZipPackage` now recognizes the central
directory archive extra data record signature `0x08064b50` (`PK\x06\x08`) and
keeps package import fail-closed before exposing document parts:

- `ZipPackage::archiveExtraDataRecordPreflight()` reports archive-extra records
  at the central-directory prefix, central-directory tail, and between the
  central directory and EOCD.
- `ZipPackage::fromString()` now rejects those records with an explicit
  unsupported archive-extra-data diagnostic instead of a generic central
  directory tail/header error.
- The WordPress ZIP package preflight smoke now includes this policy in its
  package review output.

## Evidence

Baseline focused run before edits:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1030 assertions, 0 failures`

Final focused run after implementation:

`php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

Result: `1 test files, 1054 assertions, 0 failures`

Focused delta: `+1` PHP PASS case, `+24` focused assertions.

Example smoke:

`php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`

Result: `zip package writer preflight self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `1530 -> 1531`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1949 -> 1950`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 185`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`
central-directory parsing, raw EOCD/archive-layout preflight, in-memory ZIP
fixtures, the WordPress ZIP package preflight example, and the focused PHP test
harness.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive,
external archive tool, online service, live provider test, or live-service
provider test was executed.

## Non-overlap

This does not repeat accepted ZIP central-directory signature provenance,
strict central-directory signature rejection, split archive disk-marker
diagnostics, ZIP64 EOCD/locator/extra-field/data-descriptor rejection, invalid
DOS timestamps, Unicode-name collision handling, central/local Unicode path
metadata consistency, Unix symlink/special-file rejection, DOS hidden/system/
volume attribute strict review, or trailing raw-deflate payload integrity.

## Follow-up

Next ZIP/OPC package work should stay bounded to non-overlapping native package
preflight such as additional central/local metadata provenance, ZIP64
locator/accounting diagnostics, or OPC package integration needed by
DOCX/ODT/EPUB readers.
