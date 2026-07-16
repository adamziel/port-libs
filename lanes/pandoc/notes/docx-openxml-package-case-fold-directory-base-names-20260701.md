# DOCX OpenXML Package Case-Fold Directory Base Names

Bead: `plib-bmkjo`

Slice: `docx-openxml-package-case-fold-directory-base-names`

## Scope

- Added `caseFoldDirectoryBaseName` to each DOCX/OpenXML package inventory part.
- Added `partCaseFoldDirectoryBaseNames` to `packageProvenance.summary` so
  reviewer handoff can group package parts whose containing directory basenames
  differ only by case or Unicode normalization.
- Summary buckets retain directory basename variants, directory/depth/top-level
  counts, role counts, content-type source/base counts, part names, byte totals,
  and largest-part digest provenance.

## Fixture

- Added `DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php`.
- The fixture covers repeated `_rels` directories and mixed-case `MEDIA`,
  `Media`, and `media` directory basenames, including relationship-target roles
  and a missing content-type member.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php`
  - `1 test files, 46 assertions, 0 failures`
- Post-rebase DOCX/OpenXML gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldPathsTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php`
  - `6 test files, 12510 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
