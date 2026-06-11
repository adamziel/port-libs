# DOCX custom property aggregate values

Bead: `plib-hchyg`

Scope:
- Preserve DOCX `docProps/custom.xml` aggregate custom property payloads from `vt:vector` and `vt:array`.
- Keep typed scalar conversion for vector/array members, including strings, integers, booleans, and filetime text.
- Expose aggregate property metadata on each custom-property item: declared value count, actual value count, base type, array lower bound, and per-member value types.
- Preserve the structured values through `docx.customProperties`, `meta.customProperties`, and `meta.docxCustomProperties`.

Assertions:
- Added `preserves docx custom property vector and array values from package properties`.
- Adds 29 focused DOCX OpenXML assertions.

Verification:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 1 file, 2225 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 files, 73107 assertions, 0 failures.

Boundary:
- No Pandoc binary, Word, LibreOffice, office suite, zip/unzip command, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
