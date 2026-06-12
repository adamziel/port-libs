# DOCX/OpenXML embedded font package provenance

Bead: `plib-3g11b`

Implemented bounded DOCX/OpenXML package ingestion provenance for `fontTable.xml` embedded font relationships. Embedded font targets are reported as metadata-only `embeddedFonts` review records with font variant, `fontKey`, subset flags, relationship IDs, target query/fragment suffixes, content-type parameter provenance, byte length, CRC32, missing/external/unexpected-content-type diagnostics, and package inventory `embedded-font` roles.

Embedded font bytes stay blocked from document media handoff through `byteExposurePolicy: embedded-font-bytes-blocked`; the reader does not parse, decode, deobfuscate, execute, or expose font payload bytes.

Focused coverage adds one `DocxOpenXmlReaderTest.php` PASS case with 85 assertions. Verified after rebasing onto current main `0dfe5caf66`:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (1 file, 1491 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 69153 assertions)

No Pandoc, Word, LibreOffice, office suites, zip/unzip, font engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
