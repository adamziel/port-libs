# ODF Package Directory Base Name Stems

Bead: `plib-awb7g`

Slice: `odf-package-directory-base-name-stems`

## Scope

- Added `directoryBaseNameStem` and `caseFoldDirectoryBaseNameStem` to ODF
  package path-shape provenance.
- Added `packageDirectoryBaseNameStem*` and
  `packageCaseFoldDirectoryBaseNameStem*` inventory fields to compact
  `OpenDocumentPackage` summaries and rich `OdfReader` package provenance.
- Buckets group package entries by the stem of the basename of their containing
  package directory, preserving raw directory basename variants such as
  `Pictures.assets`, `pictures.raw`, and `pictures`.
- Each stem bucket retains entry counts, file/directory counts, package
  directory counts, media-type buckets, role and byte-exposure rollups, entry
  names, and largest-entry metadata.

## Fixture

- Added `OdfPackageDirectoryBaseNameStemInventoryTest.php`.
- The fixture covers dotted root and nested directory basenames, mixed-case
  stem variants, extensionless directory basenames, directory entries grouped
  with contained files, declared media/text parts, and an undeclared sidecar
  entry.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php`
  - `1 test files, 119 assertions, 0 failures`
- Related ODF/OpenDocument package gate:
  `php tools/run-tests.php lanes/pandoc/tests/OdfPackageDirectoryBaseNameStemInventoryTest.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfPackageBasenameInventoryTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `8 test files, 7985 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
