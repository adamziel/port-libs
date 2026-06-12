# EPUB OCF Metadata Sidecar Provenance

Bead: `plib-gcivz`

Base: current `origin/main` `70c9c9ef1f1c03fe64618e77ca0358ecc3687f2c`

## Summary

EPUB3 package handoff now recognizes `META-INF/metadata.xml` as an inert OCF
metadata sidecar review item.

- OCF metadata sidecars report ZIP byte provenance.
- OCF metadata sidecars validate the EPUB metadata root namespace.
- OCF metadata sidecars keep `ocf-sidecar-metadata-only` byte exposure policy.
- The sidecar summary propagates into WordPress package review metadata.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 test file, 1698 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67788 assertions, 0 failures

## Accounting

- Adds one focused `EpubPackageTest.php` PASS case with 38 assertions.
- `phpPass` moves from 3151 to 3152; `phpFail` remains 0.
- Adds `mappedEpubOcfMetadataSidecarCases = 1`.
- Adds `epubOcfMetadataSidecarAssertions = 38`.

## Boundaries

No Pandoc, EPUBCheck, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
