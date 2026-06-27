# DOCX Font Table Embedded Font External Policy

Slice: `plib-7jqg4`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now carries external-target policy metadata for font-table
embedded font relationships. The `fontTable` payload and
`packageProvenance.summary` distinguish allowed external font targets from
unsafe schemes, preserve unsafe target lists, scheme/kind buckets, and issue
codes, and continue to keep external font targets unfetched and embedded font
bytes blocked.

This stays bounded to metadata-only DOCX package review. It does not invoke
Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

Validation:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` - 1 file, 10,325 assertions, 0 failures.

Parity accounting:

- `phpPass`: `459 -> 460`
- `phpFail`: `0`
- `mapped`: `2305 -> 2306`
- `mappedDocxFontTableEmbeddedFontExternalTargetPolicyCases`: `1`
