# DOCX/OpenXML vbaProject macro package provenance

Bead: `plib-b4p1o`

Implemented bounded DOCX/OpenXML package ingestion provenance for document-level `vbaProject` relationships. Macro project targets are reported as metadata-only `macroProjects` review records with relationship IDs, target query/fragment suffixes, content-type parameter provenance, byte length, CRC32, external/missing/unexpected-content-type diagnostics, and package inventory `macro-project` roles.

Macro project bytes stay blocked from document media handoff through `byteExposurePolicy: macro-project-bytes-blocked`; the reader does not execute, parse, decompress, or expose VBA payload bytes.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case with 84 assertions. Verified after rebasing onto current main `65bad4e34f97`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 1450 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 68909 assertions)

No Pandoc, Word, LibreOffice, office suites, zip/unzip, macro engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
