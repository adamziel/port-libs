# DOCX OpenXML Package Directory Base Name Stems

Bead: `plib-i2t8k`

Slice: `docx-openxml-package-directory-base-name-stems`

## Scope

- Added `directoryBaseNameStem` and `caseFoldDirectoryBaseNameStem` to each
  DOCX/OpenXML package inventory part.
- Added `partDirectoryBaseNameStems` and
  `partCaseFoldDirectoryBaseNameStems` to `packageProvenance.summary` so
  reviewer handoff can group package parts whose containing directory basenames
  differ by extension suffix or case.
- Summary buckets retain directory basename variants, stem variants,
  directory/depth/top-level counts, role counts, content-type source/base
  counts, part names, byte totals, and largest-part digest provenance.

## Fixture

- Added `DocxOpenXmlPackagePartDirectoryBaseNameStemsTest.php`.
- The fixture covers dotted `media.assets`/`MEDIA.assets`/`media.raw`
  directory basenames, extensionless `media` directories, repeated `_rels`
  directories, relationship-target roles, and a missing content-type member.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryBaseNameStemsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryBaseNameStemsTest.php`
  - `1 test files, 80 assertions, 0 failures`
- DOCX/OpenXML package gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryBaseNameStemsTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldPathsTest.php lanes/pandoc/tests/DocxOpenXmlPackageBasenameInventoryTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `6 test files, 12280 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 212 assertions, 0 failures`
- Post-rebase DOCX/OpenXML gate:
  `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartDirectoryBaseNameStemsTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldDirectoryBaseNamesTest.php lanes/pandoc/tests/DocxOpenXmlPackagePartCaseFoldPathsTest.php lanes/pandoc/tests/DocxOpenXmlPackageBasenameInventoryTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxReaderTest.php`
  - `7 test files, 12520 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check origin/main...HEAD -- lanes/pandoc`
- Conflict-marker scan across changed lane files reported no matches.

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
