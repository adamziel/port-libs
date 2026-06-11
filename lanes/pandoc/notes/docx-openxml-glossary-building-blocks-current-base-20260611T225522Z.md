# DOCX OpenXML Glossary Building Blocks Current-Base Slice

Slice: plib-iq6l9, 2026-06-11T225522Z.

Base: refreshed onto origin/main 67332814bf, with this slice committed on top.

Scope:
- Added DOCX package ingestion for the relationship-selected word/glossary/document.xml glossary document part.
- Summarized glossary building-block docParts with names, style, categories, galleries, types, descriptions, GUIDs, block/text rollups, content-type parameters, root validation, relationship target suffix metadata, and glossary relationship sidecar counts.
- Propagated glossary summary counts into package provenance while keeping payload handling bounded and native PHP only.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`: 1 test file, 1085 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66951 assertions, 0 failures.

External tools not invoked: Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
