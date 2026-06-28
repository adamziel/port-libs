# DOCX OpenXML XML Element Subtree Provenance - 2026-06-28

## Slice

- Added metadata-only XML element subtree provenance for XML-inspectable DOCX package parts.
- Per-part inventory rows now report subtree element counts, descendant element counts, leaf-descendant counts, subtree depth spans, path buckets, element-name buckets, and detailed per-element subtree rows.
- `packageProvenance.summary` now rolls those fields up across package XML parts and exposes `partXmlElementSubtrees` for reviewer handoff.
- The detailed rows preserve namespaces, qualified names, element paths, depth, child counts, descendant counts, and leaf flags without exposing element text, attribute values, replacement text, or package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 test file, 11,563 assertions, 0 failures
