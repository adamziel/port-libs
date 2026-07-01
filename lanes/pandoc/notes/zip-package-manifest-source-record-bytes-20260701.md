# ZIP package manifest source record byte totals

Slice: `plib-4yop4`
Date: 2026-07-01

## Change

- `ZipPackage::packageManifestPreflight()` now exposes `sourceRecordBytes` for
  each manifest entry and at the package aggregate level.
- The byte total is the metadata-only sum of the local ZIP record span and the
  central-directory record span already carried by the manifest.
- DOCX and ODF package provenance summaries now promote the aggregate as
  `zipPackageManifestSourceRecordBytes` / `packageManifestSourceRecordBytes`, so
  review dashboards can account source-record bytes without recomputing entry
  spans.

## Coverage

- Extended existing ZIP manifest fixtures for stored, deflated, extra-field,
  central-comment, and data-descriptor entries.
- Extended DOCX and ODF package provenance mirror tests so compact/rich package
  identities carry the promoted aggregate.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php -l lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,315 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 11,961 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfZipPackageManifestAggregateProvenanceTest.php`
  - 1 file, 337 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP ZIP/OPC package metadata and does not invoke Pandoc, office
suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
