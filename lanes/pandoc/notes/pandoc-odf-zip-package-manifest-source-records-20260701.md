# ODF ZIP Package Manifest Source And Size Records

ODF compact package inventories and rich reader package provenance now carry the ZIP
package manifest's package-level source records under the `zipPackageManifest*`
aggregate namespace:

- package source summary
- archive SHA-256
- central-directory offsets, byte counts, and SHA-256
- central-directory to EOCD gap offsets, byte counts, and SHA-256
- end-of-central-directory offsets, byte counts, and SHA-256
- package comment offsets, byte counts, and SHA-256
- size profile metadata, including expansion ratio, largest entry, zero-byte
  entries, and unknown expansion-ratio entries

The values are metadata-only projections from `ZipPackage::packageManifestPreflight()`;
no archive bytes or central-directory signature payload bytes are exposed.

Verification:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`
- `git diff --check origin/main -- lanes/pandoc`
