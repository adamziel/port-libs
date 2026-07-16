# DOCX content type override basename stems

`DocxOpenXmlReader` now summarizes `[Content_Types].xml` override target basename stems as metadata-only package provenance.

The content-types handoff and package summary expose case-sensitive and case-folded override basename-stem counts, duplicate stem lists, duplicate declaration counts, and duplicate groups with existing/missing declaration counts, content-type buckets, directory/extension buckets, role and issue counts, and largest existing declared target digests. Missing override targets remain metadata-only and no override target bytes are exposed.

Focused validation covers existing, missing, and mixed-case override declarations in `DocxOpenXmlContentTypeOverrideBaseNameStemsTest.php`.
