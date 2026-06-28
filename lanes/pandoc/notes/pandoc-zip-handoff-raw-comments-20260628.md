# ZIP Handoff Raw Comment Provenance

Slice: `plib-en267` shared ZIP/OPC selected-entry handoff raw comments.

## Change

- `ZipPackage::entryHandoffPreflight()` now reports readable handoff raw-comment provenance for ready entries:
  - `handoffCommentedEntryCount`
  - `handoffRawCommentProvenanceEntryCount`
  - `handoffLegacyEncodedCommentEntryCount`
  - `handoffUnicodeCommentExtraEntryCount`
  - `handoffDecodedCommentDiffersFromRawCommentEntryCount`
  - `handoffCommentedEntries`
  - `handoffRawCommentProvenanceEntries`
- Blocked selected entries keep their per-entry comment metadata but stay out of the readable handoff comment summaries.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,293 assertions, 0 failures

## Boundary

This is a native PHP ZIP/OPC metadata slice. It does not invoke Pandoc, office suites, TeX/browser engines, ZipArchive, zip/unzip, external validators, or network services.
