# DOCX/OpenXML ZIP package manifest digest provenance - 2026-06-28

Slice: `plib-6hnd7`, DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now carries metadata-only ZIP package manifest digest provenance from native `ZipPackage::packageManifestPreflight()` into DOCX package review. The provenance is exposed through `zipPackage.packageManifest`, package inventory rows, and `packageProvenance.summary`, including package manifest version/SHA-256, local-header SHA-256 digests, compressed-data SHA-256 digests, central-directory record SHA-256 digests, byte offsets, and compression rollups.

The slice keeps DOCX package bytes blocked: it exposes hashes, offsets, counts, and policy fields only, and preserves the existing `docx-zip-entry-metadata-only` byte exposure policy.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case with 52 assertions. Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 11,787 assertions, 0 failures)

Lane accounting:

- `lane-status.json` `phpPass`: `472 -> 473`
- `UPSTREAM_TEST_MANIFEST.json` mapped checks: `2310 -> 2311`
- Added `mappedDocxZipPackageManifestDigestCases: 1`

No Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
