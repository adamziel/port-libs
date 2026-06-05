# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T061131Z`
Base accepted HEAD: `02b29ee7e89e42a1c2518ec8dddaabdb2f1c6960`

## Behavior

- Added bounded rewrite-context diagnostics for OPC `TargetMode="External"`
  relationship targets that are relative references or fragment references.
- `OpcRelationshipGraph::preflightTargetsForSource()` now canonicalizes the
  relationship source part and carries:
  - `externalTargetRequiresBaseUri`
  - `externalTargetRewriteBasePart`
  - `externalTargetRewriteReason`
- The same fields flow through `preflightOfficeDocumentRoot()`,
  `preflightEmbeddedPackages()`, and `reachableTargetsForSource()`.
- Safe external relative links remain valid; the new fields tell WordPress
  import code that caller-supplied source/base-URI rewrite policy is still
  required before publishing those links.
- The WordPress DOCX OPC preflight example now includes a relative external
  reviewer link and self-tests the rewrite diagnostic.

## Source Truth

- OPC relationship targets may be URI references, and `TargetMode="External"`
  targets can point outside the package without being absolute URLs.
- Import pipelines need to distinguish stable absolute external links from
  package/source-relative external references before handing them to WordPress.
- This slice stays inside native PHP OPC relationship graph semantics. It does
  not implement full Markup Compatibility `ProcessContent` handling, encrypted
  package policy, nested embedded-package expansion, cryptographic signature
  verification, or higher-level DOCX UI treatment of OPC diagnostics.
- No Pandoc, Cabal solver/build/test command, Haskell runner, citeproc, BibTeX,
  Biber, Word, LibreOffice, office tooling, `zip`, `unzip`, external template
  engine, TeX/PDF engine, browser renderer, Typst, online sanitizer, or online
  service was executed.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 476 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 477 assertions, 1 failures`.
  - Failure: external relative relationships did not expose rewrite-context
    fields.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 491 assertions, 0 failures`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7956 assertions, 0 failures`.
  - PASS lines: `680`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Changed PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 36 to 37 PASS cases.
- Focused OPC assertions moved from 476 to 491, adding 15 assertions.
- Manifest mapped checks moved from 1157 to 1158.
- OPC target preflight mapped cases moved from 6 to 7.
- OPC target preflight assertions moved from 29 to 44.

## Dependency Closure

No new support component is needed. This reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
MIME grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, target integrity preflight, relationship-part source
validation, external target scheme policy, package-part preflight,
digital-signature relationship preflight, embedded package/object relationship
preflight, relationship Type URI diagnostics, root office-document preflight,
strict XML shape validation, OPC Markup Compatibility ignorable extension
handling, content-type-gated relationship part loading, and reachable
relationship closure traversal.

## Follow-Up

Keep full MC `ProcessContent`, `PreserveElements`, and `PreserveAttributes`
semantics, encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
OPC diagnostics as separate bounded slices.
