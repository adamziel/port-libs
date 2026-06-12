# ODT Object Replacement Package Sidecars

Hook: `plib-zumpr`, Pandoc ODF/ODT OpenDocument package ingestion core blocker slice.

## Scope

Added native PHP `OdfReader` package review handling for `ObjectReplacements/` sidecars.

- Declared, missing, encrypted, invalid-media-type, and undeclared replacement assets are reported in `packageObjectReplacements`.
- Manifest items expose `objectReplacementPackagePart` and block direct byte exposure with `object-replacement-package-bytes-blocked` unless encryption has the stricter `encrypted-resource-bytes-blocked` policy.
- Package inventory assigns `object-replacement` roles and counts `objectReplacementPartCount`.
- Replacement sidecars are excluded from document media handoff, so preview/replacement images are not treated as ordinary content images.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 4326 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 72381 assertions, 0 failures.

No Pandoc, office suites, `zip`/`unzip`, `ZipArchive`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.

## Direct-Format Accounting

- `mappedOdtObjectReplacementPackageCases`: 1
- `odtObjectReplacementPackageAssertions`: 44
- `phpPass`: 3246 -> 3247
- `phpFail`: 0
