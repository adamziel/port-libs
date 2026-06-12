# DOCX OpenXML chart embedded package provenance

Slice: `plib-fnivl`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now surfaces chart-owned embedded package relationships as
metadata-only review records. Chart parts expose `c:externalData` relationship
IDs plus chart `.rels` package targets for present, missing, external, and
unreferenced workbook payloads. The handoff preserves relationship target
query/fragment suffixes, content-type parameter maps, CRC32/SHA-256 provenance,
package inventory roles, and aggregate summary counters while keeping workbook
bytes out of document media.

Direct-format parity accounting:

- `phpPass`: `3263 -> 3264`
- `phpFail`: `0`
- `mappedDocxOpenXmlChartEmbeddedPackageCases`: `0 -> 1`
- `docxOpenXmlChartEmbeddedPackageAssertions`: `0 -> 58`

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 2194 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 73017 assertions, 0 failures`

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, office suite, `zip`,
`unzip`, `ZipArchive`, browser renderer, external validator, online service,
live provider test, or live-service provider test was executed.
