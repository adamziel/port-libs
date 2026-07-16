# Shared OPC Embedded Package Graph Summary

Slice: `plib-o55ob`

Added a bounded native OPC summary API for embedded package graph expansion:

- `OpcRelationshipGraph::embeddedPackageGraphSummary()` now aggregates nested embedded-package graph readiness by source relationship part.
- The summary reports expanded, blocked, external, missing, parse-error, nested relationship source, and nested closure stop counts without exposing embedded package bytes.
- Focused coverage extends `OpenPackagingConventionsTest.php` over the existing nested workbook fixture, including expanded workbook, malformed embedded ZIP, external package, and missing package cases.

Validation:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 4515 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `279 test files, 107807 assertions, 10526 failures`
  - Broad lane remains blocked by the existing non-slice failure baseline, so this branch is not pre-verified.
