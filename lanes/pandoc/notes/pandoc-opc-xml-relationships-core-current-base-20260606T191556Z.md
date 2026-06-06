# OPC XML Relationships Current-Base Slice - 2026-06-06

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260606T191556Z`
Accepted base: `bb917acd81d1fad49fba55aebd1c4cf923641f0d`

## Behavior

`OpcRelationshipGraph` now exposes package-part relationship reference inventory for import review:

- Every package part is reported with content type, relationship-part metadata, source loading state, and package-part preflight issues.
- Internal relationship targets add direct referrer provenance by source part, relationship id, type, target, validity, and target issues.
- A reachable closure from the selected source relationship type adds depth-aware provenance so DOCX import can separate package-level relationships from the office-document import graph.
- Missing internal relationship targets are included as invalid inventory rows instead of disappearing from review output.
- Existing but unreferenced media/package parts stay visible with zero direct and reachable references.

The WordPress DOCX OPC preflight smoke now includes `packagePartReferences`, media reference provenance, and unreferenced media parts for reviewer/import decisions.

## Evidence

Red check after adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Result: `1 test files, 1238 assertions, 1 failures`
- Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::packagePartReferenceInventory()`

Green focused check after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- Result: `1 test files, 1283 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
- Result: `opc docx preflight self-test ok`

Status delta:

- `phpPass`: `1391 -> 1392`
- mapped denominator: `1804 -> 1805`
- New focused assertions: `+45`

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, `OpcPackagePath`, the WordPress DOCX OPC preflight example, and the focused lane test harness. Full Pandoc package-reader parity, XML digital signature canonicalization/digest/trust validation, encrypted package handling, office-suite validation, external XML tooling, and upstream Pandoc/Haskell runner parity remain separate bounded follow-up work.

## Non-Overlap

This avoids the already accepted OPC relationships slices for signature relationship-transform content-type query preflight, content-type inventory grouping, reachable closure traversal, and Pack URI part-name validation. The slice only owns package-part reverse reference inventory and DOCX preflight media provenance; it did not run Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online services, live provider tests, or live-service provider tests.
