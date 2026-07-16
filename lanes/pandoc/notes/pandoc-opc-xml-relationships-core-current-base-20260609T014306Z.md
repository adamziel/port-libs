# OPC Signature Manifest Transform Payload Fingerprints

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T014306Z`

Base accepted HEAD: `9ab19c9e2380838c7ca01f28e9b3c5ee81262c5f`

## Behavior

This slice carries bounded relationship-transform payload provenance into the OPC digital-signature manifest reference review metadata.

`OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` already materializes selected relationship XML payloads for XMLDSig relationship transforms. Manifest cross-checks now preserve each matching transform payload byte count and SHA-256 fingerprint both on the individual target match rows and as per-manifest-reference aggregate arrays.

This lets DOCX/OPC import review packets distinguish a package part that is referenced by a relationship transform from the exact relationship-transform XML payload that selected it, without attempting cryptographic XMLDSig validation.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2933 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` failed with `1 test files, 2904 assertions, 1 failures` because `relationshipTransformPayloadByteCounts` was absent on digital-signature manifest references.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2943 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case, `+10` assertions.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- PHP syntax checks passed for `OpcRelationshipGraph.php`, `OpenPackagingConventionsTest.php`, and `wordpress-docx-opc-preflight.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.
- Root harness: not run - isolated micro-slice.

## Movement

- `lane-status.json` `phpPass`: `2072 -> 2073`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2484 -> 2485`.
- `opcRelationshipGraphSupportCases`: `13 -> 14`.
- `mappedOpcRelationshipGraphSupportCases`: `13 -> 14`.
- Added `mappedOpcRelationshipManifestTransformPayloadFingerprintCases: 1`.
- `opcRelationshipGraphAssertions`: `210 -> 220`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `OpcRelationshipGraph`, accepted relationship-transform materialization, digital-signature manifest metadata preflight, the focused OPC test suite, and the lane-local WordPress DOCX OPC preflight example. No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted OPC content-type inventory, Pack URI validation, relationship Id validation, relationship target preflight, reachable closure traversal, relationship-transform selector parsing, relationship-transform ContentType query preflight, package object policy, digest policy, encrypted/embedded package policy, or XMLDSig cryptographic validation. It only propagates already-materialized relationship-transform payload byte/hash provenance into manifest-reference match metadata.

## Follow-up

Keep cryptographic XMLDSig verification, canonical XML transforms, and external XML validator parity out of scope for this lane. A useful next OPC slice is bounded DOCX reader consumption of the new manifest-reference payload fingerprints or additional relationship selector edge cases.
