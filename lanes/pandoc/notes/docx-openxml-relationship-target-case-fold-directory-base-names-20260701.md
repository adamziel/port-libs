# DOCX OpenXML Relationship Target Case-Fold Directory Base Names

Bead: `plib-jvm62`

Slice: `docx-openxml-relationship-target-case-fold-directory-base-names`

## Scope

- Added `relationshipTargetCaseFoldDirectoryBaseNames` to DOCX/OpenXML package
  provenance summaries.
- Buckets group internal relationship targets by the case-folded basename of
  their containing target directory, preserving raw directory basename variants
  such as `MEDIA`, `Media`, and `media`.
- Each bucket retains existing/missing target counts, directory counts, content
  type and relationship type rollups, target roles, source/relationship IDs, and
  largest existing target digest provenance.

## Fixture

- Added `DocxOpenXmlRelationshipTargetCaseFoldDirectoryBaseNamesTest.php`.
- The fixture covers mixed-case internal media target directories, a missing
  target resolved by default content type, and an external target that must stay
  out of internal target buckets.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldDirectoryBaseNamesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldDirectoryBaseNamesTest.php`
  - `1 test files, 44 assertions, 0 failures`
- Related DOCX/OpenXML gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldDirectoryBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetCaseFoldPartsTest.php lanes/pandoc/tests/DocxOpenXmlRelationshipTargetNameCharactersTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php`
  - `5 test files, 12356 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
