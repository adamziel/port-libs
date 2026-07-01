## DOCX Package Role Area/Depth Slice

`DocxOpenXmlReader` package-part role summaries now include metadata-only top-level
segment counts, sorted top-level segment lists, directory-depth buckets, and the
deepest part for each role bucket.

This keeps DOCX package review bounded while making it easier for import gates to
localize roles such as embedded packages, relationship sidecars, media targets,
root relationship targets, and untyped custom XML parts by package area without
reading blocked payloads or invoking external tools.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 9,987 assertions and 0 failures.

Direct-format parity remains active: this slice only advances native DOCX/OpenXML
package ingestion metadata and does not shell out to Pandoc, office suites,
browser engines, TeX, Node, unzip/zip, or external validators.
