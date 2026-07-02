# DOCX/OpenXML XML sibling transitions

This slice keeps DOCX/OpenXML package ingestion native to PHP and adds
metadata-only XML sibling-transition provenance for XML package parts.
`DocxOpenXmlReader` now reports ordered adjacent element pairs under each
parent element, parent paths, previous/next qualified-name buckets, same-name
versus different-name counts, and interleaved non-element node counts without
exposing XML text, attribute values, package bytes, or relationship target
bytes.

Focused validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with 1 file, 12,543 assertions, 0 failures.

No Pandoc binary, Office suite, TeX/browser/Typst engine, `zip`, `unzip`,
external validator, online service, live provider, or live-service provider
test was used.
