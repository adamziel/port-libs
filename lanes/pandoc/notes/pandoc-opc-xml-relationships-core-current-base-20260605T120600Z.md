# Pandoc OPC XML Relationships Core Slice

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T120600Z`

Base: `ee870bfe781c6a15fd507d5c5749acf3414a1925`

## Behavior

- Added bounded selector element shape diagnostics for OPC digital-signature
  relationship transforms.
- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now
  reports:
  - `unsupported-relationship-transform-selector-attribute`
  - `unsupported-relationship-transform-selector-child`
  - `unsupported-relationship-transform-selector-content`
- Valid `SourceId` and `SourceType` values are still collected, so WordPress
  review packets can show both the malformed signature selector markup and the
  relationships that would have been selected.
- Updated the DOCX OPC preflight example with a malformed selector fixture and
  self-test coverage for the new guard.

## Source Truth

- Bounded to OPC content-types/relationships XML package semantics for
  digital-signature `RelationshipTransform` selectors.
- Relationship transform selectors identify relationships by `SourceId` or
  `SourceType`; selector records are empty selector elements, so unexpected
  attributes, child elements, and non-whitespace text are preflight issues.
- This stays on the native PHP package-support path. No Pandoc runner, Haskell
  build, Word, LibreOffice, zip/unzip, external XML-signature tooling, or
  online service was used.

## Verification

- Baseline before adding the focused expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 740 assertions, 0 failures`.
- Red-first after adding the focused selector-shape expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 748 assertions, 1 failures`.
  - Failure: malformed selector elements were still reported as valid.
- Focused after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 751 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

## Status Delta

- `phpPass`: `886 -> 887`.
- Mapped benchmark denominator: `1344 -> 1345`.
- Added manifest counters:
  - `mappedOpcRelationshipTransformSelectorShapeCases = 1`
  - `opcRelationshipTransformSelectorShapeAssertions = 11`

## Non-Overlap

- Avoided the accepted OPC relationship content-type, Id validation, external
  target, package-part consistency, office document root, digital-signature
  origin/signature, embedded package/object, relationship selector,
  relationship transform materialization, signature transform, singular group
  selector, and reachable closure traversal slices.
- This patch only adds selector element shape diagnostics inside the existing
  relationship transform preflight path.

## Dependency Closure

- No new support component is needed. The slice reuses `OpcRelationshipGraph`,
  `OpcRelationships`, `ZipPackage`, and the existing DOCX OPC preflight example.
- Full byte-for-byte XML Signature canonicalization, Reference URI content-type
  query validation, digest verification, and full Pandoc DOCX runner parity
  remain separate bounded follow-up work.
