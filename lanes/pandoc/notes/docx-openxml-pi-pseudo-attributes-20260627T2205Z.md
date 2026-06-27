# DOCX/OpenXML Processing Instruction Pseudo Attributes

Slice: DOCX OpenXML package ingestion XML processing-instruction pseudo-attribute provenance for `plib-oaoh6`.

## Scope

- `DocxOpenXmlReader` now promotes package-wide XML processing-instruction pseudo-attribute metadata into `packageProvenance.summary`.
- The summary preserves aggregate pseudo-attribute counts, unique attribute-name counts, name buckets, sorted attribute-name lists, and total pseudo-attribute value byte lengths.
- Existing per-instruction metadata-only rows still carry target names, data byte lengths, CRC32/SHA-256 digests, and pseudo-attribute value digests without exposing PI data values or package bytes.

## Boundedness

This stays inside native PHP DOCX/OpenXML package ingestion under `lanes/pandoc`. It does not invoke Pandoc, Word, LibreOffice, office suites, `zip`/`unzip`, ZipArchive, browser renderers, TeX/PDF engines, external validators, online services, live provider tests, or live-service provider tests.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

Focused DOCX/OpenXML validation passed with 1 test file, 10,571 assertions, and 0 failures.
