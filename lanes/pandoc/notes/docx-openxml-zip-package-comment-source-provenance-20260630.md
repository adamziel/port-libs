# DOCX/OpenXML ZIP Package Comment Source Provenance - 2026-06-30

## Slice

`plib-ozrk9` carries source ZIP EOCD package-comment byte-range provenance through DOCX/OpenXML package ingestion.

## What changed

- `ZipPackage` exposes metadata-only EOCD package-comment source provenance for constructed and raw ZIP package preflights.
- `DocxOpenXmlReader` mirrors the package comment source availability, offset, byte count, end offset, and SHA-256 into `packageProvenance.summary`.
- The DOCX package comment review surface keeps decoded comment text out of package provenance while preserving source byte-range metadata for importer review.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

This stays inside native PHP ZIP and DOCX/OpenXML package ingestion. No Pandoc, office suite, ZIP CLI, browser, TeX, Node, or external validator is invoked.
