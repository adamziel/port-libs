# Shared ZIP/OPC Package Reference Suffix Provenance

Slice: `plib-whqjk` / 2026-06-10T194051Z.
Rebased: `2026-06-11T003245Z`.
Base after rebase: `d6ebfd9833f108c9c51ffc7913eea8e19776a0ce`.

Implemented a bounded shared OPC package graph handoff improvement in native PHP:

- `OpcRelationshipGraph::packagePartReferenceInventory()` now carries relationship target query, fragment, and same-source reference provenance on both direct and reachable package-part references.
- `OpcRelationshipGraph::packagePartRelationshipCoverageSummary()` now aggregates query, fragment, and same-source reference counts for direct and reachable coverage.
- Added focused `OpenPackagingConventionsTest.php` coverage for same-source fragment/query targets flowing into package-wide reference inventory and coverage summaries.

Verification:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> 1 file, 3977 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 44 files, 61736 assertions, 0 failures

Accounting:

- `phpPass`: `3029 -> 3030`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3169 -> 3170`
- Added `mappedOpcPackageReferenceSuffixCases=1`
- Added `opcPackageReferenceSuffixAssertions=16`

No Pandoc, Cabal/Haskell runner, browser renderer, external validator, online service, zip/unzip, office suite, TeX/PDF engine, Node, Jupyter, live provider test, or live-service provider test was executed.
