# ZIP Package Manifest Basename Rollups

Slice: `plib-33fgb`

## Summary

`ZipPackage::packageManifestPreflight()` now records shared ZIP/OPC package
basename provenance before format-specific inventories run:

- each manifest entry includes `packagePartBaseName`,
  `packagePartCaseFoldBaseName`, `packagePartBaseNameStem`, and
  `packagePartCaseFoldBaseNameStem`;
- package manifests expose exact basename summaries, case-fold basename
  summaries, duplicate basename groups, and duplicate case-fold basename groups;
- file entries also contribute case-fold basename-stem summaries so repeated
  logical file stems are visible independently of extension case;
- all new manifest fields are included in the deterministic
  `zip-package-manifest-v1` hash payload.

The summaries keep compressed/uncompressed byte totals, local/source record
bytes, data-descriptor byte counts, extension-key counts, directory-root counts,
and entry-name provenance with the rest of the native bounded ZIP manifest.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `git diff --check -- lanes/pandoc/src/ZipPackage.php lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 5409 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `2 test files, 10569 assertions, 0 failures`

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command,
ZipArchive, Node tooling, external validator, online service, live provider
test, or payload-expanding external tool was invoked.
