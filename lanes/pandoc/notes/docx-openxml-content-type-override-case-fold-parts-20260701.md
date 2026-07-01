## DOCX content type override case-fold parts - 2026-07-01

- Added metadata-only summary fields for content type override part names that collide after Unicode-aware case folding.
- Kept this separate from exact duplicate override declaration preflight, so reviewers can distinguish repeated declarations from distinct case variants.
- Covered existing and missing override targets, content type parameter buckets, byte rollups, and largest existing part provenance.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlContentTypeOverrideCaseFoldPartsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlContentTypeOverrideCaseFoldPartsTest.php`
