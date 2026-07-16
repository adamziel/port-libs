# Pandoc EPUB3 Compact Language Metadata Slice

Date: 2026-06-10 UTC
Bead: plib-opkc

## Scope

Compact EPUB package ingestion now preserves OPF `dc:language` details in
`EpubPackage` metadata and WordPress review summaries. The bounded native PHP
handoff records:

- raw and normalized language tags;
- primary, script, region, and variant subtags;
- duplicate language-tag diagnostics;
- invalid bounded BCP47-style tag diagnostics;
- `display-seq` refinements on language metadata.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - 1 file / 872 assertions / 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files / 61246 assertions / 0 failures

## Accounting

- `lane-status.json` `phpPass`: 3004 -> 3005
- `lane-status.json` `phpFail`: 0
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 3158 -> 3159
- Added `mappedEpubCompactLanguageMetadataCases = 1`
- Added `epubCompactLanguageMetadataAssertions = 45`

## Non-Overlap

This does not repeat accepted rootfile diagnostics, itemref refinement
provenance, nav/NCX diagnostics, rendition metadata, bindings, encryption,
fallback chains, or rich `EpubReader` language reporting. It ports the
language review shape into the compact `EpubPackage` ingestion primitive so
package preflight callers can inspect OPF language metadata without invoking
Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.
