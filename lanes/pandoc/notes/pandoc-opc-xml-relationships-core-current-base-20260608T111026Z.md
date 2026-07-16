# OPC Relationship Type Policy Inventory

Slice: `pandoc-opc-xml-relationships-core-current-base-20260608T111026Z`
Base: `01ba3eaa0944b4717c660abd6dc1418c3de0715f`
Date: 2026-06-08 UTC

## Behavior

`OpcRelationshipGraph::relationshipTypeInventory()` now decorates known OPC relationship types with bounded package-policy metadata:

- `knownRole`
- `sourceScope`
- `singletonScope`
- `policyValid`
- `policyIssues`

The policy table covers package-root singleton roles (`officeDocument`, core/extended/custom properties, digital-signature origin) and per-source thumbnail singleton checks. This is an inventory/reporting slice only; it does not change package loading, target resolution, relationship traversal, or DOCX rendering.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2129 assertions, 0 failures`
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2130 assertions, 1 failures`
  - Expected failure: relationship type inventory rows had no `knownRole` policy metadata.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 2158 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

Focused delta: `+1` PHP PASS case and `+29` focused assertions.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1625` -> `1626`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2044` -> `2045`
- OPC relationship graph support cases: `13` -> `14`

## Dependency Closure

No new native PHP support component is needed. The slice reuses `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and the existing WordPress DOCX OPC preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not duplicate the accepted OPC content-type inventory, Pack URI validation, package-root external target policy, direct loader content-type guard, digital-signature relationship role, signature relationship transform ContentType query, relationship closure traversal, or relationship ID/target validation slices.

## Follow-Up

Next OPC work should choose a non-overlapping package relationship semantics gap such as using policy inventory in DOCX import reports, relationship transform manifest coverage, or additional target-mode/content-type diagnostics.
