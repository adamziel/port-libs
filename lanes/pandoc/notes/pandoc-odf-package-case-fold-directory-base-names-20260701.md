# ODF Package Case-Fold Directory Base Names

Bead: `plib-6qpsw`

Slice: `odf-package-case-fold-directory-base-names`

## Scope

- Added `directoryBaseName` to ODF package path-shape provenance.
- Added `packageDirectoryBaseName*` and `packageCaseFoldDirectoryBaseName*`
  inventory fields to compact `OpenDocumentPackage` summaries and rich
  `OdfReader` package provenance.
- Buckets group package entries by the case-folded basename of their package
  directory while preserving raw directory basename variants such as `Pictures`
  and `pictures`.
- Each case-fold bucket retains entry counts, file/directory counts, package
  directory counts, media-type buckets, role and byte-exposure rollups, entry
  names, and largest-entry metadata.

## Fixture

- Added `OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php`.
- The fixture covers mixed-case package directories at root and nested depths,
  directory entries grouped with their contained files, declared media/text
  parts, and an undeclared sidecar entry.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php`
  - `1 test files, 70 assertions, 0 failures`
- Related ODF/OpenDocument package gate:
  `php tools/run-tests.php lanes/pandoc/tests/OdfPackageCaseFoldDirectoryBaseNameInventoryTest.php lanes/pandoc/tests/OdfPackageBasenameInventoryTest.php lanes/pandoc/tests/OdfPackagePartExtensionProvenanceTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OpenDocumentReaderTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - `7 test files, 7866 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
