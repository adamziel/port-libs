# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T064654Z`
Base accepted HEAD: `16abed1ad9fae5cddf75eba6926644d7427f2294`

## Behavior

- `OpcPackagePath::resolveInternalTarget()` now rejects raw ASCII whitespace
  and control bytes in internal OPC relationship `Target` URI references.
- Percent-encoded spaces such as `media/raw%20space.png` remain valid and
  resolve to package part names containing spaces.
- `OpcRelationshipGraph::preflightTargetsForSource()` now reports
  `internal-target-invalid-uri-byte` alongside `invalid-target` when a package
  relationship uses a raw space/control byte in an internal target.
- The WordPress DOCX OPC preflight smoke exposes the new diagnostic in its
  import-review integrity packet.

## Source Truth

- OPC relationship `Target` values are URI references in `.rels` XML records;
  package part names with spaces must be represented in relationship XML as
  URI-escaped target paths, not raw whitespace-bearing URI text.
- This slice stays inside native PHP OPC package semantics and does not claim
  Haskell Pandoc runner parity.

## Evidence

- Accepted focused baseline from the previous OPC lane state:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  was `1 test files, 1115 assertions, 0 failures`.
- Focused OPC verification after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 1119 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  printed `opc docx preflight self-test ok`.

## Status Delta

- Focused OPC PASS cases: `65 -> 66`.
- Focused OPC assertions: `1115 -> 1119`, adding 4 assertions.
- Lane `phpPass`: `1232 -> 1233`.
- Manifest mapped checks: `1675 -> 1676`.
- OPC relationship target preflight cases: `6 -> 7`.

## Dependency Closure

No new support component is needed. This slice reuses the accepted native PHP
`OpcPackagePath`, `OpcRelationships`, `OpcRelationshipGraph`, `ZipPackage`,
`XmlHtmlDom`, the WordPress DOCX OPC preflight example, and lane-local
manifest/status machinery.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, XMLDSig validator, external XML tool, online service, or live
provider test was executed.

## Non-Overlap

This is additive on top of accepted OPC content-type parsing, relationship XML
namespace/record-shape validation, XML NCName-style relationship Id validation,
TargetMode value diagnostics, percent-encoded target path handling, internal
absolute/network/traversal/encoded-slash target diagnostics, external target
policy, relationship-part load decisions, package inventories, and reachable
closure traversal.

It does not touch Markdown/HTML readers or writers, DOCX body parsing beyond
the OPC preflight example, ODT, EPUB3, YAML, CSL/BibTeX, doctemplates, table
geometry, math/TeX, PDF, legacy DOC/CFB, syntax highlighting, charset/Unicode,
archive compression, ZIP package primitives, or upstream-runner dependency
audit behavior.

## Follow-Up

Keep XML canonicalization, digest/signature validation, encrypted package
policy, broader relationship-transform parity, and deeper DOCX/EPUB/ODF reader
integration as separate bounded slices.
