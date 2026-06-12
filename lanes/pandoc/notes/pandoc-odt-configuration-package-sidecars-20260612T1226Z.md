# ODF/ODT Configuration Package Sidecars

## Summary

ODF/ODT package ingestion now classifies `Configurations2/` package entries as
inert `configuration-package` sidecars in both compact `OpenDocumentPackage`
review summaries and the full `OdfReader` package provenance path.

The slice preserves stored byte length, compressed length, compression method,
and stored CRC provenance for declared and undeclared configuration sidecars,
but blocks document-media byte exposure with
`configuration-package-bytes-blocked`. `Configurations2/` images therefore stay
out of document media handoff while remaining visible in package review queues.

## Accounting

- `phpPass`: `3208 -> 3210`
- `phpFail`: `0`
- `mapped`: `3237 -> 3239`
- `mappedOdfConfigurationPackageSidecarCases`: `2`
- `odfConfigurationPackageSidecarAssertions`: `49`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 773 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - `1 test files, 4268 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 71119 assertions, 0 failures`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.
