# OPC ZIP manifest content-type summaries

## Scope

- Slice: `pandoc-opc-zip-manifest-content-type-summary`
- Base: current main `2ddf03c4d1`
- Area: shared ZIP/OPC package manifest preflight before OPC graph construction.

## Change

- `OpcRelationshipGraph::preflightZipEntryManifest()` now emits compact `contentTypeSummaries` rows keyed by resolved content type.
- The manifest preflight also emits `contentTypeSourceSummaries` rows for default, override, and missing content-type sources.
- Summary rows preserve entry counts, file/package counts, compressed and uncompressed byte totals, role counts, handoff-kind counts, entry names, and canonical part names.
- Coverage exercises default XML, default relationship, default media/binary, override document/package, missing default-extension, and missing extensionless inventory buckets without invoking external ZIP, office, or validation tooling.

## Direct-Format Accounting

- `phpPass`: `3522 -> 3527`
- `phpFail`: `0`
- `mappedOpcZipManifestContentTypeSummaryCases`: `5`
- `opcZipManifestContentTypeSummaryAssertions`: `7`
- `UPSTREAM_TEST_MANIFEST.upstream.mapped`: `3439 -> 3444`

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 4326 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `46 test files, 83449 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No Pandoc binary, office suite, zip/unzip, ZipArchive, browser renderer,
external validator, online service, live provider test, or live-service provider
test was invoked.
