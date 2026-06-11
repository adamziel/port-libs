# pandoc-docx-openxml-package-inventory-crc32-current-base-20260611T234056Z

Slice: `plib-0b696`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `8086676050`.

## Scope

DOCX package provenance already tracked package part names, byte lengths,
content-type resolution, relationship sidecars, and missing content-type
summary rows. This slice adds deterministic CRC32 review provenance to the
package inventory and propagates it into missing content-type summary rows.

## Change

`DocxOpenXmlReader` now records `crc32` on each
`docx.packageProvenance.parts` entry. Untyped package parts that enter
`partsWithoutContentType` carry the same checksum alongside part name, byte
count, default extension, relationship-part state, and package roles.

The focused case covers `[Content_Types].xml`, root relationships,
`word/document.xml`, `word/_rels/document.xml.rels`, media, and an untyped
custom XML payload.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test file, 1171 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67339 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
