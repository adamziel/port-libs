# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T100648Z`
Base accepted HEAD: `1420277bc6031a522c9261ef52aa1ee5c7c3d325`

## Behavior

- `OpcPackagePath::resolveInternalTarget()` now rejects percent-encoded `.` and `..` target path segments before package path normalization.
- Raw `./` and `../` relative targets still resolve normally inside the OPC package.
- `OpcRelationshipGraph::preflightTargetsForSource()` reports `invalid-target` plus `internal-target-unsafe-percent-encoded-dot-segment` for encoded dot-segment targets.
- The WordPress DOCX OPC preflight example carries the same diagnostic in `integrity.internalTargetDiagnostics`.

## Source Truth

OPC relationship `Target` values are URI references resolved relative to their source part. This bounded PHP support path keeps normal raw relative path resolution but prevents percent-encoded dot segments from bypassing target preflight before DOCX/OPC import review.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1119 assertions, 0 failures`.
- Red-first: the new encoded dot-segment expectations failed with `1 test files, 1116 assertions, 1 failures`; the encoded target reported no issues before the implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1143 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

## Status Delta

- Focused OPC PASS cases: `65 -> 66`.
- Focused OPC assertions: `1119 -> 1143` (`+24`).
- Lane PHP pass count: `1293 -> 1294`.
- Manifest mapped checks: `1707 -> 1708`.
- OPC relationship target preflight cases: `6 -> 7`.
- OPC relationship target preflight assertions: `29 -> 53`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OpcPackagePath`, `OpcRelationships`, `OpcRelationshipGraph`, `ZipPackage`, XML/libxml parsing, the WordPress DOCX OPC preflight example, and the focused PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat content-type parsing, relationship XML namespace/shape validation, NCName Id validation, TargetMode diagnostics, raw whitespace target diagnostics, absolute/network/traversal/encoded-slash target diagnostics, external target policy, relationship part load decisions, package inventories, closure traversal, signature transform content-type query, or role content-type matching.

## Follow-Up

Keep XML canonicalization/digest/signature validation, encrypted package policy, broader relationship transform parity, and deeper DOCX/EPUB/ODF integration as separate bounded slices.
