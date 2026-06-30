# ODF core package part provenance

Slice: `plib-02uv3`

OpenDocument package ingestion now reports a metadata-only `packageCoreParts` summary for the six core ODT package parts:

- `mimetype`
- `META-INF/manifest.xml`
- `content.xml`
- `styles.xml`
- `meta.xml`
- `settings.xml`

The compact `OpenDocumentPackage` summary and rich `OdfReader` package provenance both report existence, manifest declaration status, manifest media type, ZIP byte metadata, and bounded issue codes for undeclared existing core XML parts and declared-but-missing core XML parts. Package identity hashes include this summary so core package drift changes deterministic identity without exposing XML bytes.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfPackageCorePartsProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCorePartsProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCorePartsProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCorePartsProvenanceTest.php lanes/pandoc/tests/OdfReaderTest.php`
