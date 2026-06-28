# DOCX selected OpenXML part rollups

2026-06-28 slice for `plib-v4elc`.

- Added package summary mirrors for selected XML part source, relationship, target suffix, and content-type rollups.
- Locked the rollups against the focused DOCX OpenXML fixture that mixes relationship-selected parts, conventional package parts, and fallback selections.
- Verified with `php -l lanes/pandoc/src/DocxOpenXmlReader.php`, `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`, and `php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`.
