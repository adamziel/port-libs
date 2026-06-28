# DOCX/OpenXML XML Element Child Profiles

Slice: `plib-47ncz`, DOCX/OpenXML package ingestion core blocker, 2026-06-28.

`DocxOpenXmlReader` now carries metadata-only XML element child-profile provenance for XML-inspectable DOCX package parts. Each profile records element path/name/namespace, child node kind counts, content-model buckets (`empty`, `element-only`, `text-only`, `mixed`, `metadata-only`, and `whitespace-only`), and package-wide rollups through `packageProvenance.summary`.

The slice keeps raw XML text, comments, processing-instruction data, CDATA text, and package bytes out of the review metadata. It only exposes bounded structural counters and paths.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: 1 test file, 11,316 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, TeX/PDF engines, external validators, online services, live provider tests, or live-service provider tests were invoked.
