# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T005931Z`

Base accepted HEAD: `b78fe5dad8286235b93b1e8139739180f39a0e32`

## Behavior Added

- Added bounded package-signature preflight diagnostics for relationship
  transform `Reference URI` values that carry a URI fragment.
- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now keeps
  the referenced relationship part materialized for reviewer audit, but marks
  the transform invalid with `relationship-transform-reference-has-fragment`.
- Updated the WordPress DOCX OPC preflight smoke so import audit packets expose
  the same fragment-reference guard alongside the selected relationship XML.

## Source Truth

- OPC relationship-transform signatures select relationships from a
  relationship part; the relationship part name and optional `ContentType`
  query are package-addressing inputs, while a URI fragment does not identify a
  relationship part.
- This slice stays within bounded native PHP OPC package semantics and does
  not attempt XML canonicalization, cryptographic signature validation, or
  upstream Haskell runner parity.

## Red Check

- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 949 assertions, 1 failures`.
  - Failing case: `flags OPC signature relationship transform references with fragments`
    still considered the transform valid.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 951 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests add 1 PASS case.
- New focused test coverage adds 11 assertions for the fragment-bearing
  relationship-transform reference diagnostic and preserved relationship XML.
- Lane `phpPass` moved from `1128` to `1129`.
- Manifest mapped native checks moved from `1580` to `1581`.

## Dependency Closure

No new support component is needed. This slice reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and `XmlHtmlDom` package helpers.

This slice did not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
zip/unzip, XMLDSig validators, external XML tools, online services, or live
provider tests.

## Non-Overlap

This patch is additive on top of accepted ZIP/OPC package primitives,
content-type parsing, relationship XML parsing, XML NCName-style Id validation,
relationship target preflight, package-signature relationship-transform
selector materialization, relationship-transform `ContentType` query preflight,
content-type inventory grouping, and reachable closure traversal. It does not
touch Markdown/HTML reader/writer, doctemplate, YAML metadata, CSL/BibTeX, DOCX
body parsing, ODT, EPUB3, PDF, math, legacy DOC/CFB, archive compression,
syntax highlighting, charset, or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep XML canonicalization byte-for-byte validation, cryptographic signature
verification, encrypted package policy, and richer signature-reference package
policy as separate bounded OPC slices.
