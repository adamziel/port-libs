# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T064349Z`
Base accepted HEAD: `66d1418133da4443c2300fa93a01793691d07e92`

## Behavior

- Added same-source internal OPC relationship target resolution for empty-path
  URI references in part-level `.rels` XML.
- `OpcPackagePath::resolveInternalTarget()` now resolves fragment-only and
  query-only internal targets such as `#review-bookmark` and
  `?review=ready#packet` to the relationship source part.
- Package-root relationships still reject empty-path targets because `/` is not
  a package part.
- OPC graph target preflight and reachable closure now expose same-source
  fragment/query relationships as existing package-part references with the
  source part content type.
- The WordPress DOCX OPC preflight example reports those relationships as
  `wordpressImport.internalSourceReferences` for reviewer bookmark/state
  handoff.

## Source Truth

- OPC relationship targets are URI references resolved relative to the
  relationship source. A same-document fragment or query reference should
  inherit the source part when the source is a real package part.
- This slice is bounded to content-types/relationships XML package semantics
  and package path resolution. It does not implement DOCX field rendering,
  full Markup Compatibility processing, encrypted package policy, nested
  embedded-package expansion, cryptographic signature verification, or full
  Haskell Pandoc runner parity.
- No Pandoc, Cabal solver/build/test command, Haskell runner, citeproc, BibTeX,
  Biber, Word, LibreOffice, office tooling, `zip`, `unzip`, `tar`, `lz4`,
  external template engine, TeX/PDF engine, browser renderer, Typst, online
  sanitizer, or online service was executed.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before this slice: `1 test files, 491 assertions, 0 failures`.
- Red-first focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before implementation: `1 test files, 491 assertions, 1 failures`.
  - Failure: `OPC relationship target path must not be empty`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 510 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness: not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 37 to 38 PASS cases.
- Focused OPC assertions moved from 491 to 510, adding 19 assertions.
- Lane status moved from `phpPass` 718 to 719.
- Manifest mapped checks moved from 1178 to 1179 with a new
  `mappedOpcRelationshipSameSourceTargetCases` bucket.

## Dependency Closure

No new support component is needed. This reuses accepted native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, MIME content
type grammar validation, Pack URI override normalization, relationship XML
namespace parsing, XML NCName Id validation, relationship target
percent-decoding, target integrity preflight, relationship-part source
validation, external target scheme and relative-rewrite policy, package-part
preflight, digital-signature relationship preflight, embedded package/object
relationship preflight, relationship Type URI diagnostics, root office-document
preflight, strict XML shape validation, OPC Markup Compatibility ignorable
extension handling, content-type-gated relationship part loading, and reachable
relationship closure traversal.

## Follow-Up

Keep full MC `ProcessContent`, `PreserveElements`, and `PreserveAttributes`
semantics, encrypted package policy, nested embedded package graph expansion,
cryptographic signature verification, and higher-level DOCX UI treatment of
OPC diagnostics as separate bounded slices.
