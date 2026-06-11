# Pandoc OPC Closure Stop Provenance Slice 2026-06-11

Slice: `pandoc-opc-closure-stop-provenance-current-base-20260611T1604Z`

## Scope

This slice stays inside `lanes/pandoc` and covers shared ZIP/OPC package review primitives. It extends native OPC relationship closure stop records with query, fragment, and same-source reference provenance so DOCX/EPUB/ODF package review queues can distinguish a plain closure stop from a bookmark/query-targeted package reference.

## Implementation

- `OpcRelationshipGraph::relationshipSourceClosureInventory()` now includes `targetQuery`, `targetFragment`, and `sameSourceReference` on closure stop rows.
- `relationshipSourceClosureCoverageSummary()` now reports `stopQueryTargetCount`, `stopFragmentTargetCount`, and `stopSameSourceReferenceCount`.
- Added a focused OPC package test for same-source `?query#fragment` closure stops and query-bearing unloaded target stops.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 4075 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 63772 assertions, 0 failures`

No Pandoc, office suite, zip/unzip, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was executed.

## Direct-Format Parity Accounting

- Focused OPC assertions: `4075` in `OpenPackagingConventionsTest.php`.
- Added one focused PHP PASS case.
- Lane status `phpPass` moves `3064 -> 3065`; `phpFail` remains `0`.

## Non-Overlap

This does not repeat accepted relationship target query/fragment validation, package-part reference inventory suffix preservation, OPC content-type provenance, relationship XML shape diagnostics, relationship transform selector handling, or DOCX reader integration. It only carries suffix and same-source provenance into closure stop rows and their aggregate summary counts.
