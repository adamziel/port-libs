# ODF ZIP package manifest aggregate provenance

Date: 2026-07-01
Slice: `plib-l8xvy`

ODF/ODT package ingestion now promotes aggregate fields from
`ZipPackage::packageManifestPreflight()` into both compact and rich package
review surfaces:

- `OpenDocumentPackage` package inventory and package identity expose
  `zipPackageManifest...` counters, byte totals, review-field flags,
  compression summaries, directory-root summaries, path-depth summaries, and
  central-directory/local-header order names.
- `OdfReader` package provenance and package identity expose the same aggregate
  package-manifest summary fields.

The source remains bounded native PHP ZIP manifest preflight data. This is
metadata-only provenance; it does not expose package entry bytes and does not
invoke Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Node tooling,
Jupyter, live services, or external validators.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
  - 1 file, 176 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderZipSourceRecordProvenanceTest.php`
  - 1 file, 21 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - 1 file, 52 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php`
  - 1 file, 78 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 2,162 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 file, 5,205 assertions, 0 failures

Direct-format parity remains active in lane status.
