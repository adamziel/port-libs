# DOCX relationship source role provenance

Bead: plib-ll166

Base: current main 809f019f4c

Scope: DOCX/OpenXML package provenance now classifies the source role for every relationship emitter. Package summaries expose aggregate `relationshipSourceRoleCounts` plus per-source-part role rows, and relationship-type summaries carry role buckets plus per-relationship `sourceRoles`.

Fixture: `DocxOpenXmlReaderTest.php` adds a package-root/document/header/missing-source relationship graph. The test asserts package-root counts, office-document/root-target roles, header source roles, missing-source sidecar provenance, and image relationship type source-role buckets.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php` -> no syntax errors
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 test files, 1718 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 test files, 70157 assertions, 0 failures

Accounting: `phpPass` 3184 -> 3185; `mappedDocxRelationshipSourceRoleCases` = 1; `docxRelationshipSourceRoleAssertions` = 19.
