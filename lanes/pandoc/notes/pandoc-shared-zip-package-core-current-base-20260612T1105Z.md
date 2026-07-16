# Pandoc Shared ZIP/OPC Local Header Order Manifest Provenance

Slice: `pandoc-shared-zip-package-core-current-base-20260612T1105Z`

## Behavior

- `OpcRelationshipGraph::preflightZipEntryManifest()` now carries `ZipPackage::localHeaderOrderPreflight()` into OPC ZIP manifest handoff summaries.
- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now carries `ZipPackage::centralDirectoryLocalHeaderOrderPreflight()` into raw central-directory manifest summaries before package construction.
- Each manifest entry now reports local-header order, local header offset where available, cross-order names, and whether its central-directory order matches local-header order.
- The OPC manifest remains focused on package/relationship/content-type validity; the existing strict ZIP import preflight remains the hard policy surface for `central-directory-local-header-order-mismatch`.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Focused OPC test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 4280 assertions, 0 failures`
- Full Pandoc PHP gate: `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 70733 assertions, 0 failures`

## Accounting

- `phpPass`: `3196 -> 3197`
- `phpFail`: `0`
- Added `mappedOpcZipLocalHeaderOrderManifestCases=1`.
- Added `opcZipLocalHeaderOrderManifestAssertions=39`.

## Non-Overlap

This does not repeat accepted ZIP strict import local-header order diagnostics, archive stream order inspection, ODF package order provenance, compression-method buckets, largest payload ranking, content-type override declaration summaries, ZIP64 byte sentinels, comment provenance, or local-header fixed-field checks. The new surface is only OPC ZIP manifest propagation for constructed and raw manifest preflights before DOCX/EPUB/ODF package bytes are exposed.

No Pandoc, Cabal/Haskell runner, office suite, `zip`, `unzip`, `ZipArchive`, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
