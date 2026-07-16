# DOCX/OpenXML Header/Footer Hyperlink Relationship Provenance

Issue: plib-egype
Slice: pandoc-docx-openxml-header-footer-hyperlink-relationships
Date: 2026-06-17
Base: origin/main 4c86656a04

This recovery slice adds metadata-only package provenance for hyperlink relationships declared in DOCX header and footer relationship sidecars. `DocxOpenXmlReader` now summarizes header/footer hyperlink relationship IDs, source parts, source reference metadata, target suffix/query/fragment details, referenced versus orphaned state, internal target digests/content types, missing internal targets, unsafe external targets, and package inventory role buckets without exposing hyperlink target bytes as media.

Accounting adds `mappedDocxOpenXmlHeaderFooterHyperlinkRelationshipCases = 1` and `docxOpenXmlHeaderFooterHyperlinkRelationshipAssertions = 112`. `phpPass` moves `17076 -> 17077`, `phpFail` remains `0`, the upstream manifest mapped count moves `16662 -> 16663`, the root mapped inventory moves `16631 -> 16632`, and the benchmark denominator moves `3800 -> 3801`.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` (`1 test files, 6408 assertions, 0 failures`)
- `php tools/run-tests.php lanes/pandoc/tests` (`258 test files, 178176 assertions, 0 failures`)
- JSON validation, conflict-marker scan, and `git diff --check`
