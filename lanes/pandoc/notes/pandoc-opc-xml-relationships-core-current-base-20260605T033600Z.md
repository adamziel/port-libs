# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T033600Z`
Base accepted HEAD: `41762de6274c1435dd15943e6293756fd3806571`

## Behavior

- Added bounded root `officeDocument` relationship preflight for OPC packages.
- `OpcRelationshipGraph::preflightOfficeDocumentRoot()` reports the number of
  package-root `officeDocument` relationships and returns per-relationship
  diagnostics before DOCX import chooses a main document part.
- WordprocessingML content-type validation is exposed through
  `WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES`, covering ordinary,
  macro-enabled, template, and macro-enabled template main document content
  types.
- Missing, duplicate, external, malformed, missing-target, relationship-part,
  and invalid-content-type roots stay visible as package diagnostics instead of
  letting import code silently trust the first matching relationship.
- Updated the WordPress DOCX OPC preflight example so the root review packet is
  checked before resolving document relationships, media, signatures, and
  embedded package/object handoff state.

## Source Truth

- Pandoc-style DOCX loading depends on OPC package relationships: read the
  package root `_rels/.rels`, locate the `officeDocument` relationship, and use
  its target as the main source part before parsing the document body.
- OpenXML DOCX packages identify the main WordprocessingML document through
  the package-root `officeDocument` relationship and a WordprocessingML main
  document content type.
- This slice stays bounded to native PHP OPC package semantics. It does not run
  Pandoc, validate complete OpenXML schemas, parse nested embedded packages,
  verify cryptographic signatures, or execute office tools.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 393 assertions, 0 failures`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 424 assertions, 0 failures`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 30 to 31 PASS cases.
- Focused OPC assertions moved from 393 to 424, adding 31 assertions.
- Lane status moved from `phpPass` 579 to 580.
- Manifest mapped checks moved from 1,061 to 1,062 with a new
  `mappedOpcOfficeDocumentRootPreflightCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

This slice did not invoke Pandoc, Cabal solver/build/test commands, Haskell
runners, citeproc, BibTeX, Biber, Word, LibreOffice, office tools, `zip`,
`unzip`, external template engines, TeX/PDF engines, browser renderers, Typst,
online sanitizers, online services, or package execution.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
parsing, XML NCName Id validation, relationship target percent-decoding, target
integrity preflight, relationship-part source validation, external target
policy, package-part preflight, digital-signature relationship preflight,
embedded package/object relationship preflight, relationship Type URI policy
diagnostics, and reachable relationship closure traversal.

## Follow-Up

Keep relationship XML schema strictness, external relative-reference rewrite
policy, encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
officeDocument root diagnostics as separate bounded slices.
