# ODF ZIP package-manifest entry comment rollups

Hook: plib-1psac

ZipPackage now reports package-manifest entry-comment rollups, and OpenDocumentPackage plus OdfReader project those rollups through compact and rich ODF package provenance:

- `zipPackageManifestHasEntryComments`
- `zipPackageManifestCommentedEntryNames`
- `zipPackageManifestEntryCommentSummaryCount`
- `zipPackageManifestEntryCommentSourceRecordBytes`
- `zipPackageManifestEntryCommentSummaries`

The summaries remain metadata-only. They include entry names, central-directory offsets and byte counts, comment hashes, source-record byte totals, and an explicit `zip-entry-comment-source-metadata-only` exposure policy, but they do not expose raw entry-comment bytes or invoke Pandoc, office suites, ZIP tools, validators, or live services.

Validation before submit:

- `git diff --check`
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- conflict-marker scan of changed files
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` (3 files, 12,764 assertions, 0 failures)
