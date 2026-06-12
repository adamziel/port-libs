# EPUB OCF Manifest Sidecar Provenance

Bead: `plib-j1h7b`

Base: current `origin/main` `67f05ca4d2d96f2b395bafddeec72196622ef38d`

## Summary

EPUB3 package handoff now recognizes `META-INF/manifest.xml` as an inert OCF
manifest sidecar review item in the compact `EpubPackage` preflight path.

- OCF manifest sidecars report ZIP byte provenance and metadata-only exposure.
- OCF manifest sidecars validate the ODF manifest root.
- Declared, missing, encrypted, invalid, and size-mismatched references remain
  visible for package review.
- The sidecar summary exposes `manifestPresent` and propagates into WordPress
  package review metadata.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1660 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67639 assertions, 0 failures

## Accounting

- Accounts one focused `EpubPackageTest.php` PASS case with 54 assertions.
- `phpPass` remains 3151 on the rebased lane head; `phpFail` remains 0.
- Adds `mappedEpubOcfManifestSidecarCases = 1`.
- Adds `epubOcfManifestSidecarAssertions = 54`.

## Boundaries

No Pandoc, EPUBCheck, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
