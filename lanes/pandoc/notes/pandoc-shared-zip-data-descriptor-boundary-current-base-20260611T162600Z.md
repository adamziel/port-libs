# Pandoc Shared ZIP Data Descriptor Boundary Current Base 20260611T162600Z

## Scope

- Lane: `pandoc`
- Micro-slice: shared ZIP/OPC package primitive
- Base: `origin/main` `4c7bc3880`
- Behavior: raw ZIP data-descriptor preflight for descriptors swallowed by overstated central-directory compressed sizes.

## Implementation

`ZipPackage::dataDescriptorIntegrityPreflight()` now recognizes when the expected descriptor boundary lands on the next ZIP record instead of descriptor bytes. The summary preserves:

- `data-descriptor-missing-before-next-local-header`
- descriptor bytes before the next record
- descriptor boundary kind
- ZIP record signature hex/name at the boundary

The guard only applies when fewer than 12 descriptor bytes are available, so valid unsigned descriptors whose CRC bytes resemble ZIP signatures remain parseable.

## Evidence

- Red-first focused run failed on the missing boundary diagnostic.
- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test file, 3170 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63908 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `3069 -> 3070`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3193 -> 3194`
- Added `mappedZipDataDescriptorBoundaryCases = 1`
- Added `zipDataDescriptorBoundaryAssertions = 24`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
