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

- `phpPass`: `3303 -> 3304`
- `phpFail`: `0`
- `mappedOdfConfigurationPackageSidecarCases`: `3 -> 4`
- `odfConfigurationPackageSidecarAssertions`: `141 -> 229`

Verification on current base `e9fb37d55a`:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- progress.md PANDOC_STATUS.md lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/src/OpenDocumentPackage.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 1154 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 74156 assertions, 0 failures

No Pandoc, LibreOffice, office suites, `zip`/`unzip`, `ZipArchive`, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.
