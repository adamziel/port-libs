# OPC ZIP central directory source record provenance

Date: 2026-06-27
Slice: `plib-54n6q`

## Change

- `OpcRelationshipGraph::preflightZipEntryManifest()` now carries central-directory
  source-record provenance from the shared ZIP package manifest into each OPC ZIP
  entry row:
  - `centralDirectoryIndex`
  - `centralDirectoryRecordOffset`
  - `centralDirectoryRecordBytes`
  - `centralDirectoryRecordEnd`
  - `centralDirectoryRecordSha256`
- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now exposes the
  same central-directory record offset/byte/hash vocabulary in raw central-directory
  entry rows before package construction succeeds.

## Coverage

- Added a focused OPC manifest case that builds one ZIP package, runs both the
  constructed-package and raw central-directory manifest preflights, and verifies
  the central-directory record offsets, lengths, and SHA-256 hash against the
  actual archive bytes.

## Validation

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,649 assertions, 0 failures
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests`
  - 294 files, 116,692 assertions, 9,781 failures
  - Broad lane remains baseline-red outside this ZIP/OPC slice; visible
    failures include `lanes/pandoc/tests/YamlMetadataReviewTest.php`.

No external validators, `zip`/`unzip`, Pandoc, office tools, browser tooling,
TeX engines, or Node tooling were used.
