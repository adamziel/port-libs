# EPUB OCF Metadata Sidecar Provenance

Bead: `plib-gcivz`
Base: current main `0339a50490`
Date: 2026-06-12 UTC

## Scope

EPUB3 package handoff now recognizes `META-INF/metadata.xml` as an inert OCF
metadata sidecar review item.

- Reports ZIP byte, compression, CRC32, and hash provenance.
- Validates the EPUB metadata root namespace before review handoff.
- Keeps `ocf-sidecar-metadata-only` byte exposure policy.
- Adds `metadataPresent` sidecar accounting and WordPress package review propagation.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 1699 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 67922 assertions, 0 failures`

Lane status moved `phpPass` `3154 -> 3155`; `phpFail` remains `0`.

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests were invoked.
