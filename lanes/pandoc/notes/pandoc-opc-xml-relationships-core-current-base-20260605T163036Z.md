# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T163036Z`
Base accepted HEAD: `d35739fb1421e2e65d55da1e5c9f8fc25164043c`

## Behavior Added

- Added `OpcRelationshipGraph::contentTypeInventory()` as a bounded native PHP
  package audit grouped by OPC `ContentType`.
- The inventory reports package parts by content type, override-backed versus
  default-backed parts, relationship part sources, internal relationship target
  references, reachable target parts, missing override-only parts, invalid
  relationship-part content-type issues, and aggregate issue codes.
- Updated `wordpress-docx-opc-preflight.php` so WordPress review packets can
  group DOCX package media, XML metadata, relationship parts, signatures, and
  missing override-only parts before import routing.

## Source Truth

- Pandoc-style DOCX loading depends on OPC `[Content_Types].xml` plus root and
  part-local `.rels` files to select the office document, media, core
  properties, signatures, embeddings, and related XML parts.
- OPC package review needs both content-type mapping and relationship graph
  semantics: a part can exist through a default extension, through an override,
  through an internal relationship target, or only as a stale override entry.
- This slice stays bounded to native PHP OPC package graph review and does not
  parse WordprocessingML body content or validate cryptographic signatures.

## Evidence

- Red-first focused OPC check:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 863 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\OpcRelationshipGraph::contentTypeInventory()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 898 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 51 to 52 PASS cases.
- Focused OPC assertions moved from 863 to 898, adding 35 assertions.
- Lane `phpPass` moved from `998` to `999`.
- Manifest mapped native checks moved from `1453` to `1454`.
- Added `mappedOpcContentTypeInventoryCases = 1` and
  `opcContentTypeInventoryAssertions = 35`.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and the existing WordPress DOCX OPC preflight example.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, Word, LibreOffice, `zip`, `unzip`, XMLDSig validators, external XML
tools, external template engines, TeX/PDF engines, browser renderers, online
sanitizers, or online services.

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
materialization, relationship-part load audits, signature relationship
transform reference content-type query preflight, and relationship Type
inventory.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep encrypted package policy, nested embedded-package graph expansion,
cryptographic XML signature validation, byte-for-byte C14N verification, and
higher-level DOCX UI treatment of content-type inventory diagnostics as
separate bounded slices.
