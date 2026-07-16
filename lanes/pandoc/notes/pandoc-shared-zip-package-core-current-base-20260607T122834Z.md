# Pandoc ZIP Package Directory CRC Slice

Base accepted HEAD: `e57c0bcf9b6e3ffa5b25f24a078d7756e1f0a24a`

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260607T122834Z`

## Behavior

`ZipPackage` now rejects zero-length directory entries whose local and central ZIP metadata advertises a nonzero CRC32. Directory entries have no file data, so accepting a nonzero CRC let a malformed `word/media/` directory look like a readable package member with suspicious integrity metadata before DOCX/ODT/EPUB or WordPress media handoff.

The slice preserves normal empty directory entries with CRC32 `00000000` and empty reads.

## Red-First Evidence

Before the implementation, a focused probe accepted a `word/media/` directory entry with CRC32 `0000007b` and `read('word/media/')` returned an empty string.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 1024 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - `zip package writer preflight self-test passed`

Final syntax, JSON, and whitespace verification is recorded in the handoff response.

## Status Delta

- `lane-status.json` `phpPass`: `1498 -> 1499`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1918 -> 1919`
- `zipPackageCoreSupportCases`: `22 -> 23`
- `mappedZipPackageCoreSupportCases`: `22 -> 23`
- `zipPackageCoreAssertions`: `161 -> 164`

## Dependency Closure

No new support component is needed. This reuses the native PHP `ZipPackage` reader, focused ZIP package tests, and the existing WordPress ZIP package preflight example.

No Pandoc, Cabal/Haskell runner, zip/unzip, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP64 rejection, Unix symlink rejection, drive-letter path rejection, Unicode-name collision handling, central-directory digital-signature provenance, invalid DOS timestamp preflight, or trailing raw-deflate byte rejection. It is limited to directory-entry CRC integrity.

Root harness status: not run - isolated micro-slice.
