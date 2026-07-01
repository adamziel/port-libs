## DOCX relationship source case-fold parts - 2026-07-01

- Added metadata-only summary fields for relationship source parts that collide after Unicode-aware case folding.
- Kept package-root pseudo-sources out of the collision summary while preserving existing and missing package-part sources.
- Covered source counts, relationship counts, content type buckets, byte rollups, relationship sidecars, and largest existing source provenance.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlRelationshipSourceCaseFoldPartsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipSourceCaseFoldPartsTest.php`
