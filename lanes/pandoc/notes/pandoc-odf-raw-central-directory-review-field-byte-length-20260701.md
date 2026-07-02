# ODF/ODT raw central-directory review field byte lengths

Slice: `plib-ymtzs` ODF/ODT OpenDocument package ingestion blocker.

`OpenDocumentPackage::rawImportPreflight()` now exposes metadata-only
central-directory review-field byte-length buckets before ZIP or OpenDocument
package acceptance. The rollup accounts for central-directory extra-field bytes
plus raw entry-comment bytes by bucket, records entry counts, review-entry
counts, byte totals, directory roots, entry names, and longest review-field
entries without exposing extra-field or comment payload bytes.

Manifest counters:
- `mappedOdfRawPackageImportPreflightCases`: `5`
- `odfRawPackageImportPreflightAssertions`: `118`
- `mappedOdfRawCentralDirectoryReviewFieldByteLengthCases`: `1`
- `odfRawCentralDirectoryReviewFieldByteLengthAssertions`: `31`

Focused validation:
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageRawImportPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageRawImportPreflightTest.php`

No external Pandoc, office suite, TeX/browser engine, Node, zip/unzip,
validators, or live services were invoked.
