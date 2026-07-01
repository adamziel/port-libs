# DOCX OpenXML Relationship Target Fragment Buckets

`plib-vsnib` extends DOCX package relationship provenance with metadata-only target fragment buckets.

The slice keeps package bytes blocked while exposing distinct relationship target fragment labels, per-fragment relationship counts, external/internal counts, target existence counts, content-type rollups, relationship type rollups, and source/relationship/target part references through `packageProvenance.summary`.

Focused coverage:

- `lanes/pandoc/tests/DocxOpenXmlRelationshipTargetFragmentBucketsTest.php`
