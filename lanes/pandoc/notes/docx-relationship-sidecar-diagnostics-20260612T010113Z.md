# DOCX Relationship Sidecar Diagnostics - 2026-06-12

Slice: `plib-2kf75`
Base: `99cd6d2022`

DOCX/OpenXML package provenance now reports malformed non-critical relationship sidecars without aborting package ingestion. Extra `.rels` parts beyond the package-root and selected document relationship parts are preflighted before package-wide provenance/inventory traversal; malformed sidecars keep their package inventory entry and source-part metadata while relationship records are suppressed with bounded diagnostics.

Added provenance fields on relationship parts:

- `validXml`, `validRoot`, `xmlParseError`, `rootNamespace`, `rootLocalName`
- `issues` with `invalid-relationship-part-xml` and `unexpected-relationship-part-root`
- package summary rollups for relationship-part issue counts and affected sidecars

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 1390 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` - 44 files, 68192 assertions, 0 failures
