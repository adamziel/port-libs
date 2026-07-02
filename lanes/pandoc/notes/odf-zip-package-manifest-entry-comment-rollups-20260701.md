# ODF ZIP package-manifest entry comment rollups

Hook: plib-1psac

OpenDocumentPackage and OdfReader now project shared ZipPackage package-manifest entry-comment rollups through compact and rich ODF package provenance:

- `zipPackageManifestHasEntryComments`
- `zipPackageManifestCommentedEntryNames`
- `zipPackageManifestEntryCommentSummaryCount`
- `zipPackageManifestEntryCommentSourceRecordBytes`
- `zipPackageManifestEntryCommentSummaries`

The fields reuse `ZipPackage::packageManifestPreflight()` and remain metadata-only. They do not expose package payload bytes or invoke Pandoc, office suites, ZIP tools, validators, or live services.

Validation before submit:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php` (1 file, 868 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` (2 files, 7,615 assertions, 0 failures)
