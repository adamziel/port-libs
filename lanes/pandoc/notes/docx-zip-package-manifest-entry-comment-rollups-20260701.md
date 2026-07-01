# DOCX ZIP package-manifest entry comment rollups

Hook: plib-icy55

DocxOpenXmlReader now projects the shared ZipPackage package-manifest entry-comment rollups into DOCX package ingestion provenance:

- `zipPackageManifestHasEntryComments`
- `zipPackageManifestCommentedEntryNames`
- `zipPackageManifestEntryCommentSummaryCount`
- `zipPackageManifestEntryCommentSourceRecordBytes`
- `zipPackageManifestEntryCommentSummaries`
- matching `packageManifest...` aliases on `packageProvenance.zipPackage`

The fields are metadata-only and reuse `ZipPackage::packageManifestPreflight()` output. They do not expose package payload bytes, entry comment text beyond the bounded manifest summary, or invoke Pandoc, office suites, ZIP tools, validators, or live services.

Validation before submit:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 12,531 assertions, 0 failures)
