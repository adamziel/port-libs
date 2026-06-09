# Pandoc ZIP Package Core Current-Base Slice - 2026-06-09T030236Z

## Behavior

- Extended `ZipPackage::centralDirectoryInventoryPreflight()` with bounded central-directory recovery metadata:
  - `scanStoppedOffset` and `scanCompletedCentralDirectory`.
  - `hasUnexpectedCentralDirectoryTail`, `unexpectedRecordOffset`, and `unexpectedRecordSignatureHex`.
  - `hasCentralDirectoryEocdGap`, `centralDirectoryEocdGapOffset`, `centralDirectoryEocdGapBytes`, and `isCentralDirectoryEocdGapExplainedBySignature`.
- Raw strict ZIP import preflight now carries those details through the existing `centralDirectoryInventory` component before constructor failure, so DOCX/EPUB/ODT review queues can distinguish an EOCD gap from bytes incorrectly counted inside the central directory.
- Extended `wordpress-zip-package-preflight.php` with self-tested central-directory gap and unexpected counted-tail fixtures.

## Verification

Baseline before edits:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2201 assertions, 0 failures`

Red-first/context:

- The initial focused test fixture used arbitrary central-directory/EOCD gap bytes and failed with `ZIP end-of-central-directory record not found`, proving the fixture exercised EOCD discovery rather than the central-directory inventory layer.
- The fixture was narrowed to recognizable archive-extra-data records so the bounded EOCD candidate filter stays in scope while the inventory preflight reports recoverable gap/tail metadata.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- Result: `1 test files, 2235 assertions, 0 failures`
- Assertion delta: `+34`
- PASS-line delta: `+1`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
- Result: `zip package writer preflight self-test passed`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP EOCD, central-directory, raw strict ZIP import preflight, and in-memory ZIP fixtures. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted stored-first mimetype descriptor policy, central-directory local-header offset diagnostics, Unicode filename hygiene, entry-count mismatch direction, external-attribute policy, ZIP64 end/extra-field reporting, split archive detection, data descriptor integrity, unsupported compression/encryption policy, local-header metadata mismatch, central-directory signatures, or archive extra-data record detection. It only adds recovery metadata to the existing central-directory inventory summary.

## Next

Good follow-ups are ZIP64/data-descriptor edge diagnostics, DOCX/EPUB/ODT reader consumption of strict package preflight diagnostics, or remaining package media policy gaps as separate native PHP slices.
