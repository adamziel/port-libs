# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T030541Z`
Base accepted HEAD: `7e8350b1ef3db6f47e1658b3972bdea83e44a6f0`

## Behavior

- Added bounded OPC embedded package/object relationship preflight.
- `OpcRelationshipGraph::preflightEmbeddedPackages()` now classifies
  `http://schemas.openxmlformats.org/officeDocument/2006/relationships/package`
  as `embedded-package` and
  `http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject`
  as `embedded-object`.
- Internal embedded package targets are checked against
  `application/vnd.openxmlformats-officedocument.package`; OLE object targets
  are checked against
  `application/vnd.openxmlformats-officedocument.oleObject`.
- The preflight reuses existing relationship target validation, so missing
  package parts, relationship-part targets, unsafe external schemes, and
  malformed relationship types stay visible in one review record.
- Updated the WordPress DOCX OPC preflight example so embedded workbook and OLE
  object parts are separated from ordinary media before import review.

## Source Truth

- ECMA-376 shared embedded package parts use content type
  `application/vnd.openxmlformats-officedocument.package` and source
  relationship
  `http://schemas.openxmlformats.org/officeDocument/2006/relationships/package`:
  https://c-rex.net/samples/ooxml/e1/Part1/OOXML_P1_Fundamentals_Embedded_topic_ID0EBMCO.html
- ECMA-376 shared embedded object parts use content type
  `application/vnd.openxmlformats-officedocument.oleObject` and source
  relationship
  `http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject`:
  https://c-rex.net/samples/ooxml/e1/Part1/OOXML_P1_Fundamentals_Embedded_topic_ID0EA5BO.html
- This is bounded native PHP OPC package semantics for DOCX/OpenXML import
  preflight. It does not expand nested embedded packages into full recursive
  OPC graphs and does not validate or execute OLE content.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 361 assertions, 0 failures`.
- Red-first focused OPC test after adding embedded-package expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 361 assertions, 1 failures`; missing
    `OpcRelationshipGraph::preflightEmbeddedPackages()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 393 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6200 assertions, 0 failures`.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 29 to 30 PASS cases.
- Focused OPC assertions moved from 361 to 393, adding 32 assertions.
- Lane status moved from `phpPass` 564 to 565.
- Manifest mapped checks moved from 1,042 to 1,043 with a new
  `mappedOpcEmbeddedPackagePreflightCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, citeproc, BibTeX, Biber, Word, LibreOffice, office tools, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, Typst,
online sanitizers, online services, or OLE/package execution.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
parsing, XML NCName Id validation, relationship target percent-decoding, target
integrity preflight, relationship-part source validation, external target
policy, package-part preflight, digital-signature relationship preflight,
relationship Type URI policy diagnostics, and reachable relationship closure
traversal.

## Follow-Up

Keep external relative-reference rewrite policy, encrypted package policy,
embedded package extraction/expansion into nested OPC graphs, cryptographic
signature verification, OLE object interpretation, and higher-level DOCX UI
treatment of embedded-package diagnostics as separate bounded slices.
