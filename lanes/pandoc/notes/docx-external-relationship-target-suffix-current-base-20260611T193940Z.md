# DOCX external relationship target suffix provenance

Base: `6f71ba75aa55` (`origin/main`, 2026-06-11).
Bead: `plib-p2235`.

## Slice

DOCX OpenXML relationship package provenance now preserves query strings and fragments for external relationship targets in the same `targetQuery`, `targetFragment`, and `targetReferenceSuffix` fields already used for internal package targets.

External targets still remain outside package part resolution: `targetPart` is `null`, `exists` is `false`, and content type resolution remains missing. The change is provenance-only and does not fetch or dereference external URLs.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (28 tests, 874 assertions)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 65588 assertions)
