# OPC Content Type Override Usage Summary

Slice: `plib-b33pd` / Pandoc shared ZIP/OPC package core blocker.

## Scope

`OpcRelationshipGraph::contentTypeOverrideUsageSummary()` now summarizes OPC
content-type overrides before importer handoff:

- exact, case-equivalent, and missing override targets;
- relationship-part overrides and relationship content types on non-relationship parts;
- `[Content_Types].xml` override hazards;
- reserved `_rels` directory override hazards;
- per-content-type and per-issue counts with compact row provenance.

This is package-level review metadata only. It does not add direct-format parity
claims and does not shell out to Pandoc, office suites, zip/unzip, browser
renderers, external validators, online services, or live provider tests.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - `2 test files, 6966 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61547 assertions, 0 failures`

Lane accounting after rebase: `lanes/pandoc/lane-status.json` `phpPass`
`3018 -> 3019` on base `9b4baed9323b0076927553c1f451a8cf2b47d01c`.
