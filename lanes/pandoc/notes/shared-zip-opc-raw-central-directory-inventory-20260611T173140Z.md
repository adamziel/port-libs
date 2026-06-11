# Shared ZIP/OPC Raw Central Directory Inventory Slice

Date: 2026-06-11 UTC
Bead: `plib-nfncl`
Base target: `origin/main 2cea4fa785b868a6fa27c96e3ade52a6d7295957`

## Scope

This slice tightens `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()`
so raw OPC package manifests carry the shared ZIP central-directory inventory
preflight before package construction.

The summary now reports separate size and inventory validity, merged
central-directory issues, scanned entry counts, declared/scanned count mismatch
metadata, duplicate entry-name groups, and duplicate local-header-offset groups.
Duplicate local-header offsets now block `valid` and
`isSupportedByBoundedReader` while preserving central-directory entry roles,
content-type provenance, and byte-bucket metadata for reviewer handoff.

## Evidence

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 test file, 4121 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64414 assertions, 0 failures

## Accounting

- `phpPass`: 3082 -> 3083
- `mappedOpcRawZipCentralDirectoryInventoryCases`: 1
- `opcRawZipCentralDirectoryInventoryAssertions`: 26
- Mapped denominator: 3201 -> 3202

## Boundaries

No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.

This does not repeat accepted raw OPC central-directory role classification,
byte-bucket summaries, OPC relationship/content-type parsing, ZIP central
directory parsing, or strict ZIP package construction. It only propagates the
existing shared ZIP inventory block into the raw OPC manifest preflight path.
