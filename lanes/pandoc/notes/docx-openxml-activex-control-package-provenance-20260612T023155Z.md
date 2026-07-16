# DOCX/OpenXML ActiveX control package provenance

Implemented bounded DOCX/OpenXML package ingestion provenance for ActiveX control relationships. `DocxOpenXmlReader` now reports document `control` relationships as metadata-only `activeXControls` review records, including referenced and unreferenced control XML parts, nested `activeXControlBinary` targets, target query/fragment suffixes, content-type parameter provenance, byte length, CRC32, missing/external/unexpected-content-type diagnostics, and `activex-control` / `activex-binary` package inventory roles.

ActiveX control XML and binary bytes remain blocked from document media handoff via `byteExposurePolicy` and `reviewPolicy` metadata.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case with 81 assertions. Verified on current main `2ac38b5e1c`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 1524 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 69412 assertions)

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ActiveX runtimes, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
