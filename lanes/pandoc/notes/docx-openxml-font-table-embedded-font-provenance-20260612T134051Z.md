# DOCX/OpenXML font-table embedded font provenance

Slice: `plib-b4p1o`, DOCX OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now preflights embedded font relationships declared from
font-table XML. The compact reader records present, missing, external, missing
relationship-id, and wrong-type embedded font payloads with target
query/fragment suffixes, content-type provenance, byte length, CRC32, hashed
font-key presence, metadata-only review policy, and blocked byte exposure.

Package provenance also labels font table and embedded font parts with
`font-table` / `embedded-font` inventory roles and surfaces aggregate
font-table embedded font counts plus issue codes.

Parity accounting:

- Adds one mapped DOCX/OpenXML PHP PASS case.
- `phpPass`: `3218 -> 3219`.
- `phpFail`: remains `0`.
- Focused DOCX/OpenXML assertion delta: `+64`.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 test file, 1856 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 71493 assertions, 0 failures.

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
