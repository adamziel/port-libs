# Shared ZIP case-fold path segment manifest

Slice: `plib-79ojw`

`ZipPackage::packageManifestPreflight()` now carries package-wide case-fold path segment rollups for shared ZIP/OPC handoff. The new manifest fields expose case-fold segment summary counts, normalized segment keys, occurrence and entry counts, raw segment variants, path-index buckets, directory-root buckets, extension buckets, compression-method buckets, entry names, and source/local byte totals.

The handoff remains metadata-only: it does not expose package payload bytes and does not invoke external ZIP tools, Pandoc, office suites, or validators. ODF compact and rich package provenance now mirrors the new aggregate fields through the existing ZIP package manifest aggregate helpers.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
