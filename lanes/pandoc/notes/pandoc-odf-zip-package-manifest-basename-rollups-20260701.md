# ODF ZIP Package Manifest Basename Rollups

## Summary

- Propagated shared ZIP package manifest basename provenance into compact `OpenDocumentPackage` and rich `OdfReader` package surfaces.
- Added per-entry `zipPackageManifestPackagePartBaseName`, case-folded basename, basename stem, and case-folded basename stem fields to ODF package identity entries.
- Added aggregate exact basename, case-folded basename, and case-folded basename stem summary and duplicate rollups to ODF package inventory/provenance and package identity surfaces.
- Extended the ODF ZIP package manifest aggregate provenance test with a duplicate-basename ODT package covering exact duplicate `content.xml`, case-folded duplicate `review.png`, and duplicate stems.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php` (737 assertions)
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageBasenameInventoryTest.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageIdentityStemLookupMapsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` (7,722 assertions)
