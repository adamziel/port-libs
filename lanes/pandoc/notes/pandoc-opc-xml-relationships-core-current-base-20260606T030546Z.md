# OPC Core-Properties Relationship Preflight

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T030546Z`
Base accepted HEAD: `5bc3fade84914b1cfb203bafe4ff5b33b0e2ffc3`
Lane: `pandoc`

## Behavior Added

`OpcRelationshipGraph::preflightCoreProperties()` now checks package-root
relationships of type
`http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties`.
Core properties remain optional, but present relationships are preflighted as
package metadata:

- zero relationships: valid
- more than one relationship: `multiple-core-properties-relationships`
- external target: `external-core-properties-target`
- internal target with a non-core content type:
  `invalid-core-properties-content-type`

The returned rows preserve the existing relationship-preflight fields used by
office-document root checks, including target part, relationship type
diagnostics, content type, external target rewrite metadata, validity, and
row-level issues.

## Source Truth And Scope

This stays in the bounded OPC/content-types support-library scope for richer
DOCX/OpenXML package conversion. It does not implement full XML Signature
validation, XML canonicalization, package encryption policy, embedded-package
expansion, or higher-level DOCX UI treatment of diagnostics.

The WordPress DOCX OPC preflight example now exposes the core-properties
preflight result in its summary so import queues can see package metadata
relationship policy without running external office or XML tools.

## Verification

Baseline before the focused case:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 977 assertions, 0 failures` with 60 PASS cases.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 1007 assertions, 0 failures` with 61 PASS cases.

Example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Result: `opc docx preflight self-test ok`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1168` -> `1169`
- mapped denominator: `1618` -> `1619`
- `opcRelationshipGraphSupportCases`: `11` -> `12`
- `mappedOpcRelationshipGraphSupportCases`: `11` -> `12`
- `opcRelationshipGraphAssertions`: `58` -> `88`
- focused test delta: +1 PASS case / +30 assertions

## Dependency Closure

No new support component is needed. This slice reused native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and the existing WordPress DOCX OPC preflight example.

No Pandoc, Cabal build, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig
validator, external XML tool, online sanitizer, online service, or live
provider test was executed.

## Non-Overlap

This does not repeat prior OPC relationship target integrity, office-document
root preflight, content-type inventory grouping, reachable closure traversal,
digital-signature origin/signature content-type checks, package-signature
RelationshipTransform ContentType query preflight, or case-equivalent
relationship-transform reference normalization.

## Follow-Up

Keep XML Signature canonicalization/digest verification, encrypted package
policy, nested embedded package expansion, and higher-level DOCX UI treatment
of OPC diagnostics as separate bounded slices.
