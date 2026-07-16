# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T103207Z`
Base accepted HEAD: `edbd54e9448f3320ec7b627467caded1fab93ac8`

## Behavior Added

- Added `OpcRelationshipGraph::relationshipTypeInventory()` as a bounded
  native PHP package audit over loaded OPC relationship sources.
- The inventory groups relationships by Type URI and reports relationship
  count, source/id groups, internal/external counts, valid/invalid target
  counts, target part sets, content-type sets, relationship-type URI issues,
  and target policy/package issues.
- Updated `wordpress-docx-opc-preflight.php` so WordPress DOCX review packets
  expose media, hyperlink, embedded package/object, digital-signature, unsafe
  external target, and malformed Type URI relationship families before
  conversion code decides which resources to import.

## Source Truth

- Pandoc-style DOCX loading depends on OPC package relationships to find the
  `officeDocument` part and then related parts such as styles, footnotes,
  comments, images, hyperlinks, embeddings, core properties, and signatures.
- OPC relationship Type URIs are the package-level selector vocabulary for
  those resources; this slice stays bounded to native PHP OPC relationship
  package semantics and does not parse WordprocessingML body content.

## Evidence

- Red-first focused OPC check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 660 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::relationshipTypeInventory()`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 686 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 44 to 45 PASS cases.
- Focused OPC assertions moved from 660 to 686, adding 26 assertions.
- Lane `phpPass` moved from `836` to `837`.
- Manifest mapped native checks moved from `1296` to `1297`.
- Added `mappedOpcRelationshipTypeInventoryCases = 1` and
  `opcRelationshipTypeInventoryAssertions = 26`.

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
traversal, SourceId/SourceType selector preflight, relationship transform
materialization, and relationship-part load audits.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep full XML Signature transform parsing from signature parts, XML C14N
byte-for-byte verification, cryptographic signature validation, encrypted
package policy, nested embedded-package graph expansion, and higher-level DOCX
UI treatment of relationship-family inventory as separate bounded slices.
