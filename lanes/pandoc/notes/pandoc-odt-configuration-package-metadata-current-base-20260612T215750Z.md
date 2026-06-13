# Pandoc ODT Configuration Package Metadata Current Base

Implemented bounded native PHP ODF/ODT package ingestion provenance for compact
`Configurations2/` sidecars in `OpenDocumentPackage`.

- Added `packageConfigurations` summary metadata with declared, undeclared,
  missing, encrypted, invalid-media-type, and directory counters.
- Preserved configuration area/path/kind, media-type, byte length, CRC32,
  compression, declared-size, review policy, and byte-exposure policy metadata.
- Kept configuration package payloads out of document media handoff while
  preserving package inventory `configuration-package` roles.

Metric movement:

- `phpPass`: `3295 -> 3296`
- `phpFail`: `0`
- `mappedOdfConfigurationPackageSidecarCases`: `3 -> 4`
- `odfConfigurationPackageSidecarAssertions`: `141 -> 229`

Verification on current base `e5fdecf1ff`:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status JSON OK\n";'`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1154 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 74076 assertions, 0 failures

No Pandoc, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.
