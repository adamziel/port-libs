# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T053811Z`
Base accepted HEAD: `c89187f34342898caf9881c6ca3bd7bed3e29bfc`

## Behavior

- `OpcRelationshipGraph::fromPackage()` now loads relationship sources only
  from `.rels` package parts whose content type resolves to
  `application/vnd.openxmlformats-package.relationships+xml`.
- `.rels`-named parts typed as ordinary XML remain visible in package-part
  preflight with `invalid-relationship-content-type`, but their relationships
  no longer add hidden targets to reachable DOCX import closure.
- `preflightPackageParts()` now exposes `relationshipSourceLoaded` so
  WordPress import review packets can distinguish a present-but-skipped
  relationship part from a valid loaded relationship source.
- The WordPress DOCX OPC preflight example now includes an invalidly typed
  draft `.rels` part and confirms it is reported for triage without traversing
  its hidden image target.

## Source Truth

- OPC relationship parts are package relationship metadata parts, not arbitrary
  XML parts that happen to be named `*.rels`.
- This slice stays inside content-types/relationships XML package semantics and
  native package graph traversal. It does not implement full MC processing,
  DOCX body parsing, encrypted package policy, nested embedded-package
  expansion, or cryptographic signature verification.
- No Pandoc, Cabal solver/build/test command, Haskell runner, texmath,
  citeproc, BibTeX, Biber, Word, LibreOffice, office tooling, `zip`, `unzip`,
  external template engine, TeX/PDF engine, browser renderer, Typst, online
  sanitizer, or online service was executed.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 460 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 461 assertions, 1 failures`.
  - Failure: `/word/_rels/comments.xml.rels` was loaded as source
    `/word/comments.xml` despite its `[Content_Types].xml` override declaring
    `ContentType="application/xml"`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 476 assertions, 0 failures`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7611 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness: not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 35 to 36 PASS cases.
- Focused OPC assertions moved from 460 to 476, adding 16 assertions.
- Lane status moved from `phpPass` 656 to 657.
- Manifest mapped checks moved from 1134 to 1135 with graph support at 12
  mapped OPC relationship graph support cases and 74 graph-support assertions.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME content
type grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target percent
decoding, target integrity preflight, relationship-part source validation,
external target policy, package-part preflight, digital-signature relationship
preflight, embedded package/object relationship preflight, relationship Type
URI diagnostics, root office-document preflight, strict XML shape validation,
bounded OPC markup-compatibility ignorable extension handling, and reachable
relationship closure traversal.

## Follow-Up

Keep full MC `ProcessContent`, `PreserveElements`, and `PreserveAttributes`
semantics, external relative-reference rewrite policy, encrypted package
policy, nested embedded package graph expansion, cryptographic signature
verification, and higher-level DOCX UI treatment of OPC diagnostics as
separate bounded slices.
