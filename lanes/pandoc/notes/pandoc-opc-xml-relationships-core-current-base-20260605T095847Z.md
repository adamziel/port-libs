# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T095847Z`
Base accepted HEAD: `ea6c1e547df97830b0857c42be5a1b64c0335a43`

## Behavior Added

- Added `OpcRelationshipGraph::preflightRelationshipPartsInPackage()` as a
  bounded native PHP relationship-part load audit that runs before strict graph
  construction.
- The audit reports each `.rels` package part with its content type,
  relationship source part, whether the source exists, whether the source is
  itself relationship infrastructure, whether the part would be loaded, the
  parsed relationship count for valid relationship parts, and explicit issues
  for wrong content types, orphan relationship parts, nested relationship-part
  sources, invalid relationship part names, and malformed relationship XML.
- Existing `OpcRelationshipGraph::fromPackage()` remains strict: a malformed
  loadable `.rels` part still fails graph construction. The new audit lets DOCX
  review packets surface that failure reason before import code commits to
  graph construction.
- Updated `wordpress-docx-opc-preflight.php` to expose the relationship-part
  load audit for package-root, document, footnote, review-source, digital
  signature, and intentionally skipped draft relationship parts.

## Source Truth

- OPC relationship parts are package infrastructure named by inserting `_rels`
  next to a source part and appending `.rels`; relationship parts must have the
  package relationships content type before they are trusted as relationship
  sources.
- Pandoc DOCX import depends on this package layer to locate the
  `officeDocument` part and its related media, signatures, comments, footnotes,
  and embedded package resources. This slice keeps that work bounded to native
  PHP OPC package semantics and does not run external document converters.

## Evidence

- Baseline focused OPC check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 630 assertions, 0 failures`.
- Red-first focused OPC check after adding the load-audit expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 630 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::preflightRelationshipPartsInPackage()`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 660 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 10065 assertions, 0 failures`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 43 to 44 PASS cases.
- Focused OPC assertions moved from 630 to 660, adding 30 assertions.
- Lane `phpPass` moved from `820` to `821`.
- Manifest mapped native checks moved from `1280` to `1281`.
- Added `mappedOpcRelationshipPartLoadAuditCases = 1` and
  `opcRelationshipPartLoadAuditAssertions = 30`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, Word, LibreOffice, `zip`, `unzip`, external office tools, external
template engines, TeX/PDF engines, browser renderers, XML signature engines,
online sanitizers, or online services.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME
content-type validation, Pack URI override and relationship part
normalization, relationship XML namespace/shape parsing, XML NCName Id
validation, target integrity preflight, relationship-part source validation,
external target policy, package-part and package-consistency preflight,
digital-signature origin/signature preflight, embedded package/object
preflight, relationship Type URI diagnostics, root office-document preflight,
markup-compatibility extension policy, reachable relationship closure
traversal, SourceId/SourceType selector preflight, and relationship transform
materialization.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep full XML Signature transform parsing from signature parts, XML C14N
byte-for-byte verification, cryptographic signature validation, encrypted
package policy, nested embedded-package graph expansion, and higher-level DOCX
UI treatment of relationship-part load diagnostics as separate bounded slices.
