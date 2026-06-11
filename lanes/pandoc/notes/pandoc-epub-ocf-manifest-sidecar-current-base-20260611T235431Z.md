# EPUB OCF Manifest Sidecar Provenance

Bead: `plib-j1h7b`

Base: current `origin/main` `e87e631746178126958c0349f9869c3dd81cb0cc`

## Summary

EPUB3 package handoff now recognizes `META-INF/manifest.xml` as an inert OCF
sidecar review item.

- OCF manifest sidecars report ZIP byte provenance.
- OCF manifest sidecars validate the expected XML root.
- OCF manifest sidecars keep `ocf-sidecar-metadata-only` byte exposure policy.
- The sidecar summary propagates into WordPress package review metadata.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1636 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67397 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

## Accounting

- Adds one focused `EpubPackageTest.php` PASS case with 31 assertions.
- `phpPass` moves from 3146 to 3147; `phpFail` remains 0.
- Adds `mappedEpubOcfManifestSidecarCases = 1`.
- Adds `epubOcfManifestSidecarAssertions = 31`.

## Boundaries

No Pandoc, EPUBCheck, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
