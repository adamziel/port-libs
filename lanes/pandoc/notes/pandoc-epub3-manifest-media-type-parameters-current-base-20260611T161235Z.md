# Pandoc EPUB3 Manifest Media-Type Parameter Slice

Date: 2026-06-11
Bead: plib-6toh9

## Scope

Compact EPUB package ingestion now preserves OPF manifest `media-type`
parameter provenance in native PHP package validation. `EpubPackage` review
summaries expose each manifest item's base media type, normalized parameter map,
parameter tokens, duplicate parameter history, invalid parameter diagnostics, and
invalid MIME type diagnostics through `validationReport()` and
`summary().wordpressImport.packageValidation`.

This lets WordPress import review packets distinguish ordinary XHTML/CSS
resources with charset/profile parameters from malformed package declarations
without invoking Pandoc, EPUBCheck, `zip`/`unzip`, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 1545 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
  - `epub3 package preflight self-test ok`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66322 assertions, 0 failures`

## Accounting

- Adds one focused `EpubPackageTest.php` package-validation case for OPF
  manifest media-type parameters.
- Current lane accounting carries `mappedEpubManifestMediaTypeParameterCases = 1`
  and `epubManifestMediaTypeParameterAssertions = 36`.
- `phpFail` remains `0` under the full post-rebase Pandoc PHP gate.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile validation, OPF
metadata/manifest href suffix handling, spine itemref provenance, rootfile
diagnostics, nav/NCX validation, guide/collections, resource properties,
bindings, media overlays, fallback chains, encryption exposure, accessibility
metadata, or rich `EpubReader` media-type reports. It is limited to compact
`EpubPackage` manifest media-type parameter review diagnostics.
