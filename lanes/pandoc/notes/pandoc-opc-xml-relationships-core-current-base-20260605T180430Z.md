# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T180430Z`
Base accepted HEAD: `2c19a701a31b0f790d90d0420fa2b95cd56a6265`

## Behavior

- Updated `OpcRelationshipGraph::materializeRelationshipTransform()` XML
  materialization so internal relationships omit `TargetMode`, matching the
  accepted lower-level `OpcRelationships::toXml()` behavior.
- External relationships still emit `TargetMode="External"` in transform
  payload XML.
- Updated the WordPress DOCX OPC preflight self-test so review packets guard
  against reintroducing `TargetMode="Internal"` in selected relationship
  transform XML.

## Source Truth

OPC relationship parts use the absence of `TargetMode` as the internal-target
default, while external relationships explicitly carry `TargetMode="External"`.
This slice stays bounded to native PHP OPC package relationship-transform XML
materialization; it does not attempt XML canonicalization, cryptographic
signature validation, DOCX body parsing, or Pandoc runner parity.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 898 assertions, 0 failures`; `54` PASS cases.
- Red-first focused OPC test after adding the canonical TargetMode assertion:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 900 assertions, 1 failures`.
  - Failure: transform XML still serialized `TargetMode="Internal"` for an
    internal image relationship.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 907 assertions, 0 failures`; `55` PASS cases.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 54 to 55 PASS cases.
- Focused OPC assertions moved from 898 to 907, adding 9 assertions.
- Lane `phpPass` moved from `1030` to `1031`.
- Manifest mapped native checks moved from `1482` to `1483`.
- Added `mappedOpcRelationshipTransformTargetModeCases = 1` and
  `opcRelationshipTransformTargetModeAssertions = 9`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcRelationships`, `OpcRelationshipGraph`, and WordPress DOCX
OPC preflight example paths.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, Word, LibreOffice, `zip`, `unzip`, `ZipArchive`, XMLDSig validators,
C14N engines, external XML tools, browser renderers, online sanitizers, or
online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
parsing and inventory, relationship XML namespace/shape parsing, XML NCName Id
validation, target integrity preflight, relationship-part source validation,
external target policy and rewrite context, package-part consistency
preflight, digital-signature origin/signature preflight, embedded
package/object preflight, relationship Type URI diagnostics, root
office-document preflight, markup-compatibility extension policy, reachable
relationship closure traversal, SourceId/SourceType selector preflight,
signature reference ContentType query preflight, and relationship transform
selector materialization.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep byte-for-byte XML Signature C14N, cryptographic signature validation,
relationship-transform digest verification, encrypted package policy, nested
embedded-package graph expansion, and higher-level DOCX UI treatment of
relationship-transform payloads as separate bounded slices.
