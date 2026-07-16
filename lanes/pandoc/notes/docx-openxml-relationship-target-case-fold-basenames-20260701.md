# DOCX relationship target case-fold basenames

Slice: `plib-d4muw`

Implemented a bounded DOCX OpenXML package-ingestion provenance rollup for
internal relationship target basenames after case folding. The package summary
now reports:

- `relationshipTargetCaseFoldBaseName*` counts split by all, existing, and
  missing target relationships;
- duplicate case-fold basename buckets with relationship and target-part counts;
- per-bucket basename variants, extensions, directories, content types,
  relationship types, roles, target parts, and the largest existing target part.

The slice reuses existing relationship target resolution and package inventory
metadata. It does not change target resolution semantics, include external
relationships in internal basename buckets, invoke Pandoc, shell out to office
suites, use zip/unzip, or run external validators.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldBaseNamesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldBaseNamesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldDirectoryBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldPartsTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetNameCharactersTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipSourceCaseFoldBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
