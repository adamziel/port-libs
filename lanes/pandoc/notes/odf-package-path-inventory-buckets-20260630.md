# ODF package path inventory buckets

## Slice

`OpenDocumentPackage` and `OdfReader` now derive package path location metadata for each ZIP inventory entry:

- `directory`, `directoryDepth`, `baseName`, and `extension` on each package inventory part.
- `packagePathDirectorySummaries` with entry/file/directory counts, byte totals, manifest declaration counts, role counts, compression buckets, and byte-exposure policy buckets.
- `packagePathExtensionSummaries` with the same metadata grouped by extension, including extensionless entries and explicit directory entries.

The change stays metadata-only. It does not read new payload bytes, expose blocked sidecars, fetch external targets, or invoke office/Pandoc/validator tooling.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- Isolated `OdfReaderTest.php` path bucket closure.
- Selected ODF package inventory reader closures around ZIP order, role byte buckets, and path buckets.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipPlatformAttributesProvenanceTest.php`
