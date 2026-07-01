# OPC ZIP local header and payload source provenance

Date: 2026-06-30
Slice: `plib-nm0ok`

## Change

- `OpcRelationshipGraph::preflightZipEntryManifest()` now carries local-header
  source span metadata from `ZipPackage::packageManifestPreflight()` into OPC ZIP
  entry rows:
  - `localHeaderLength`
  - `localHeaderSha256`
  - `compressedDataOffset`
  - `compressedDataEnd`
  - `compressedDataSha256`
- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` derives the
  same fields from bounded raw ZIP local-header span preflight before package
  construction succeeds.
- Raw ZIP64 entries with unknown payload sizes keep exact compressed-payload
  offsets and hashes null instead of exposing guessed byte ranges.

## Coverage

- Added a focused OPC manifest case that builds one package with a deflated XML
  part and a stored `[Content_Types].xml` part, then verifies constructed and
  raw manifest rows against the actual archive bytes.

## Validation

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,781 assertions, 0 failures

No external validators, `zip`/`unzip`, Pandoc, office tools, browser tooling,
TeX engines, or Node tooling were used.
