# pandoc-shared-zip-opc-compression-method-provenance-current-base-20260611T182924Z

Date: 2026-06-11 UTC

Base accepted HEAD after rebase: `c0cfa42e2e`

## Scope

Implemented a bounded shared ZIP/OPC package preflight slice for compression
method provenance. `OpcRelationshipGraph::preflightZipEntryManifest()` and
`OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now expose:

- per-entry `compressionMethodName` and `compressionMethodSupported`;
- top-level sorted `compressionMethodBuckets`;
- bucketed entry counts plus compressed and uncompressed byte totals by ZIP
  method.

This preserves parity between instantiated `ZipPackage` manifest review and
raw central-directory manifest review before local-header validation or package
construction.

## Evidence

Syntax:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Focused:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Result: `1 test files, 4176 assertions, 0 failures`

Full:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `44 test files, 65117 assertions, 0 failures`

Focused delta: `+1` PASS case and `+26` assertions.
Lane status `phpPass`: `3093 -> 3094`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage`, raw central-directory inventory, and OPC manifest role/handoff
classification. No Pandoc executable, office suite, zip/unzip, ZipArchive,
browser renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Non-Overlap

This does not repeat raw central-directory role classification, OPC content-type
override preflight, relationship load decisions, ZIP compression policy gates,
or ZIP local-header metadata checks. The new behavior is only the OPC manifest
compression method provenance layer over existing package entries.
