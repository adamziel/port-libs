# pandoc-docx-openxml-largest-package-parts-current-base-20260612T021006Z

Slice: `plib-3g11b`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `2ac38b5e1c`.

## Scope

DOCX package provenance already records package part byte lengths, CRC32 values,
content-type resolution, path buckets, and package roles. This slice adds a
bounded largest-parts digest so reviewers can identify unusually large package
payloads without rendering or invoking external Office/Pandoc tooling.

## Change

`DocxOpenXmlReader` now exposes `largestPartCount`, `largestPartName`,
`largestPartBytes`, `largestPart`, and `largestParts` under
`docx.packageProvenance.summary`. The digest is sorted by byte length descending
with part name as a deterministic tie-breaker and is capped at five entries.
Each entry carries part name, directory, basename, extension, bytes, CRC32,
content-type provenance, relationship-part state, and package roles.

The focused case forces oversized media and custom XML payloads to verify
ordering, byte totals, checksums, content-type source markers, and untyped
payload provenance.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
  - `No syntax errors detected in lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 1472 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69360 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
