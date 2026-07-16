# OPC Relationship Transform Duplicate Selectors

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T023939Z`

Base accepted HEAD: `cff2757f3c2ce59e8912b5b48a787409562aacb3`

## Behavior

This slice adds bounded provenance for duplicate OPC XMLDSig relationship-transform selectors.

`OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now reports duplicate `RelationshipReference SourceId` and `RelationshipsGroupReference SourceType` values as `duplicateSourceIds`, `duplicateSourceTypes`, and selector duplicate counts. The transform row is marked invalid with `duplicate-source-id` or `duplicate-source-type`, but selected relationships remain de-duplicated for the materialized relationship XML payload so importer review packets can inspect the exact unique relationship set.

The WordPress DOCX OPC preflight example now includes a duplicate-selector signature guard and exposes the duplicate selector metadata in its review summary.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2982 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 3002 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case, `+20` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- PHP syntax checks passed for `OpcRelationshipGraph.php`, `OpenPackagingConventionsTest.php`, and `wordpress-docx-opc-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Movement

- `lane-status.json` `phpPass`: `2162 -> 2163`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2586 -> 2587`.
- `opcRelationshipGraphSupportCases`: `13 -> 14`.
- `mappedOpcRelationshipGraphSupportCases`: `13 -> 14`.
- Added `mappedOpcRelationshipDuplicateSelectorCases: 1`.
- `opcRelationshipGraphAssertions`: `210 -> 230`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `OpcRelationshipGraph`, `OpcRelationships`, `ZipPackage`, the focused OPC test suite, and the lane-local WordPress DOCX OPC preflight example. No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, signing engine, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted OPC content-type inventory, Pack URI validation, relationship Id validation, relationship target preflight, reachable closure traversal, selector shape validation, relationship-transform `ContentType` query preflight, manifest payload fingerprint propagation, package object policy, digest policy, encrypted/embedded package policy, XMLDSig cryptographic validation, or singular/plural group-reference behavior. It only surfaces duplicate selector provenance while preserving the accepted unique relationship-transform materialization behavior.

## Follow-up

Useful next OPC work is bounded DOCX reader consumption of relationship-transform manifest payload fingerprints or another non-overlapping XMLDSig relationship reference policy guard. Keep cryptographic XMLDSig verification, canonical XML engines, and external XML validators out of scope for this lane.
