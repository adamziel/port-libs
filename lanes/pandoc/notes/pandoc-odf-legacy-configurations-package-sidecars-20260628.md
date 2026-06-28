# ODF legacy Configurations package sidecars

Slice: `plib-hm1yk` ODF/ODT package ingestion.

This slice aligns legacy `Configurations/` package members with the already
supported `Configurations2/` path in both `OpenDocumentPackage` and `OdfReader`.
Legacy configuration XML and image-like sidecars now remain metadata-only under
`configuration-package-bytes-blocked`, are exposed through package configuration
review rows with their actual `packageRoot`, and are excluded from document
media and WordPress handoff.

The new focused regression covers declared, missing, and undeclared
`Configurations/` members in the compact and rich ODF readers while proving a
normal `Pictures/` image still flows through media handoff.

Validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php`
  - 1 file, 70 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php`
  - 3 files, 2,048 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php lanes/pandoc/tests/OdfReader*Package*Test.php lanes/pandoc/tests/OdfPackageIdentityRoleFlagsTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 16 files, 3,202 assertions, 0 failures after rebase

Parity accounting: no denominator change claimed. This is a package-ingestion
byte-policy and metadata provenance closure for ODF/ODT.
