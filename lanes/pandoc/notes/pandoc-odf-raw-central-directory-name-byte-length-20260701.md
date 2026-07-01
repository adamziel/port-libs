# ODF/ODT raw central-directory name byte lengths

Slice: `plib-k34sk` ODF/ODT OpenDocument package ingestion blocker.

`OpenDocumentPackage::rawImportPreflight()` now exposes metadata-only
central-directory entry-name byte-length buckets before ZIP or OpenDocument
package instantiation. The rollup records bucket counts, raw and decoded name
byte totals, directory roots, compression method names, longest entry names, and
decoded-name mismatch counts without reading or exposing package payload bytes.

Manifest counters:
- `mappedOdfRawPackageImportPreflightCases`: `4`
- `odfRawPackageImportPreflightAssertions`: `87`
- `mappedOdfRawCentralDirectoryNameByteLengthCases`: `1`
- `odfRawCentralDirectoryNameByteLengthAssertions`: `24`

Focused validation:
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageRawImportPreflightTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageRawImportPreflightTest.php`
