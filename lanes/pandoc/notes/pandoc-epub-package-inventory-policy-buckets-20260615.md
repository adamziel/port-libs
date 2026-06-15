# Pandoc EPUB Package Inventory Policy Buckets

Scope: compact EPUB3 package ingestion now aggregates package inventory byte-exposure policy buckets for native PHP import review.

## Implementation

- `EpubPackage::packageInventoryReport()` now aggregates `byteExposurePolicyCounts`, `byteExposurePolicyByteLengths`, and `byteExposurePolicyCompressedByteLengths` at the package level.
- Directory and extension inventory summaries carry the same policy counts and byte buckets so WordPress import review can locate blocked or metadata-only resource groups without reading payload bytes.
- ZIP entries using unsupported compression are now explicitly classified as `unsupported-compression-metadata-only`; encrypted and obfuscated font resources keep their stricter blocked policies.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 2737 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`: `epub3 package preflight self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 86194 assertions, 0 failures.

No Pandoc, EPUBCheck, `zip`/`unzip`, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test is used.
