# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T043736Z`
Base accepted HEAD: `14aad85c2edfecb0743214dea60386dec4cd43bb`

## Behavior

- Added bounded root-shape validation for OPC `[Content_Types].xml`.
- Added bounded root-shape validation for OPC `.rels` `Relationships` XML.
- Root namespace declarations and whitespace-only root content remain accepted.
- Unsupported root attributes, namespaced extension attributes, and
  non-whitespace root text/CDATA now fail before package relationship traversal.
- Updated the WordPress DOCX OPC preflight example so its self-test exposes the
  root-level content-types and relationships XML guards.

## Source Truth

- OPC package metadata roots are fixed package XML records: `[Content_Types].xml`
  uses a `Types` root, and `.rels` parts use a `Relationships` root.
- Previous accepted slices made the child `Default`, `Override`, and
  `Relationship` records strict. This slice applies the same bounded package
  XML policy at the root level without changing target resolution, relationship
  closure traversal, or existing diagnostics.
- This slice does not run Pandoc, Cabal, Word, LibreOffice, zip/unzip, external
  template engines, browser renderers, TeX/PDF engines, online sanitizers, or
  online services.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before new test: `1 test files, 433 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 436 assertions, 1 failures`.
  - Failure: `rejects OPC XML package roots with unexpected attributes or text
    content` accepted malformed root attributes/text.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 443 assertions, 0 failures`.
  - PASS lines: `34`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 33 to 34 PASS cases.
- Focused OPC assertions moved from 433 to 443, adding 10 assertions.
- Lane status moved from `phpPass` 626 to 627.
- Manifest mapped checks moved from 1100 to 1101 with a new
  `mappedOpcXmlRootShapeCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, `ZipPackage`, and WordPress DOCX OPC preflight example.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, target integrity preflight, relationship-part source
validation, external target policy, package-part preflight, digital-signature
relationship preflight, embedded package/object relationship preflight,
relationship Type URI policy diagnostics, root office-document preflight,
strict child-record shape validation, and reachable relationship closure
traversal.

## Follow-Up

Keep markup-compatibility extension policy, external relative-reference rewrite
policy, encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
strict XML diagnostics as separate bounded slices.
