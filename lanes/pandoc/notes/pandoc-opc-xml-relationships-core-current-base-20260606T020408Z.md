# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T020408Z`

Base accepted HEAD: `28ce1248504d246cd7ef6530c0bb360adf7265f0`

## Behavior Added

- Added a bounded package-signature preflight guard for digital-signature
  origin parts whose relationship part loads but contains no
  `digital-signature/signature` relationships.
- `OpcRelationshipGraph::preflightDigitalSignatures()` now reports
  `missing-digital-signature-signature-relationships` and marks the origin
  invalid instead of treating an empty/non-signature origin relationship part
  as a valid signed-package path.
- The WordPress DOCX OPC preflight smoke now exposes the same empty signature
  origin guard in the integrity summary.

## Source Truth

- OPC package signatures are discovered from a digital-signature origin
  relationship and then from signature relationships in that origin part's
  `.rels` part.
- A present origin `.rels` file with unrelated relationships is not enough to
  prove that a package has signature parts. This slice keeps that gap visible
  for import review.
- This is package-relationship preflight only. It does not implement XML
  canonicalization, digest calculation, cryptographic signature validation,
  certificate trust policy, encrypted package handling, or upstream Haskell
  runner parity.

## Red Check

- Baseline focused OPC test before the new case:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 969 assertions, 0 failures`.
- Red-first focused OPC test after adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed with `1 test files, 976 assertions, 1 failures`.
  Failure: the empty origin relationship part still produced `valid === true`.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 977 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests add 1 PASS case.
- Focused OPC assertions moved from `969` to `977`, adding 8 assertions.
- Lane `phpPass` moved from `1149` to `1150`.
- Manifest mapped native checks moved from `1600` to `1601`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, and the WordPress DOCX OPC preflight smoke.

This slice did not invoke Pandoc, Cabal, Haskell runners, Word, LibreOffice,
zip/unzip, XMLDSig validators, external XML tools, online services, or live
provider tests.

## Non-Overlap

This patch is additive on top of accepted ZIP/OPC package primitives,
content-type parsing, relationship XML parsing, XML NCName-style Id
validation, relationship target preflight, content-type inventory grouping,
reachable closure traversal, package-signature relationship-transform
selector materialization, relationship-transform `ContentType` query
preflight, and case-equivalent relationship-transform reference
normalization. It does not touch Markdown/HTML reader/writer, doctemplate,
YAML metadata, CSL/BibTeX, DOCX body parsing, ODT, EPUB3, PDF, math, legacy
DOC/CFB, archive compression, syntax highlighting, charset, or
upstream-runner dependency-audit surfaces.

## Follow-Up

Keep XML canonicalization byte-for-byte validation, cryptographic digest and
signature verification, certificate/trust policy, encrypted package policy,
and richer signature-reference URI policy as separate bounded OPC slices.
