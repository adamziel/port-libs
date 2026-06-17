# DOCX/OpenXML Note/Comment Hyperlink Relationships

- Bead: `plib-de295`
- Slice: `pandoc-docx-openxml-note-comment-hyperlink-relationships`
- Scope: DOCX/OpenXML package ingestion only.

## Summary

`DocxOpenXmlReader` now summarizes hyperlink relationships declared by footnote, endnote, and comment relationship sidecars. The aggregate is metadata-only and reports relationship keys, source kind/part, target suffix/query/fragment, referenced and orphaned state, internal target content type and digest metadata, unsafe external target policy, missing internal targets, issue codes, and package inventory roles without exposing hyperlink target bytes as media.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 6149 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 258 files, 177917 assertions, 0 failures
