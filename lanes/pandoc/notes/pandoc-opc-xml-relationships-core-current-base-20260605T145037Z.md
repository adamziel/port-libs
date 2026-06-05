# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T145037Z`

Base accepted HEAD: `ab0579d2d089b95ff0a65136decc676646ae544e`

## Behavior Added

- Added bounded native PHP `mc:ProcessContent` support for OPC package XML
  roots.
- `OpcMarkupCompatibility` now parses root-level `mc:ProcessContent` QName
  declarations after validating that each prefix is declared and listed in
  `mc:Ignorable`.
- `OpcContentTypes::fromXml()` and `OpcRelationships::fromXml()` now process
  package `Default`/`Override` and `Relationship` records carried inside
  explicitly selected ignorable extension wrapper elements.
- Ignorable wrappers not listed in `mc:ProcessContent` stay hidden, and
  malformed ProcessContent declarations, non-ignorable prefixes, and
  non-whitespace wrapper text remain parse errors.
- The WordPress DOCX OPC preflight example now exposes a ProcessContent
  review-packet fixture that imports processed relationship records while
  confirming hidden wrapper relationships remain untrusted.

## Source Truth

- OPC content-types and relationships XML are package-layer inputs that can
  include Markup Compatibility declarations. This slice ports only the bounded
  `mc:ProcessContent` wrapper behavior needed to recover valid package records
  from ignorable extension containers.
- This is not full Markup Compatibility or XML Signature processing. It does
  not implement PreserveElements, PreserveAttributes, C14N, digest validation,
  certificate policy, encrypted package handling, or broader DOCX body parsing.

## Verification

- Baseline focused OPC test before this slice:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 825 assertions, 0 failures`
- Red/shape check after the first implementation attempt:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 831 assertions, 1 failures`
  - Cause: the fixture used wildcard `pc:*`, correctly exposing the hidden
    wrapper. The fixture was tightened to exact `pc:Records`.
- Focused after implementation and fixture correction:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 847 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`

Final PHP lint, JSON validation, and whitespace checks are recorded in the
handoff response. Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from 51 to 52 PASS cases.
- Focused OPC assertions moved from 825 to 847, adding 22 assertions.
- Lane `phpPass` moved from `956` to `957`.
- Manifest mapped native inventory moved from `1411` to `1412`.
- Added manifest counters:
  - `opcMarkupCompatibilityProcessContentCases = 1`
  - `mappedOpcMarkupCompatibilityProcessContentCases = 1`
  - `opcMarkupCompatibilityProcessContentAssertions = 22`

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP
`OpcMarkupCompatibility`, `OpcContentTypes`, `OpcRelationships`,
`OpcRelationshipGraph`, `ZipPackage`, and `OpcPackagePath` support.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
`zip`, `unzip`, XMLDSig validator, external office tool, external template
engine, TeX/PDF engine, browser renderer, online sanitizer, or online service
was executed.

## Non-Overlap

This is additive on top of accepted OPC content-type parsing, relationship Id
validation, target integrity preflight, reachable closure traversal,
relationship selector and transform preflight, signature reference
ContentType-query preflight, relationship-source alias guards, and
case-equivalent part-name collision guards.

It does not touch Markdown/HTML reader/writer, doctemplate, YAML metadata,
CSL/BibTeX, DOCX body parsing beyond the OPC preflight example, ODT, EPUB3,
PDF, math, legacy DOC/CFB, archive compression, syntax highlighting, charset,
or upstream-runner dependency-audit surfaces.

## Follow-Up

Keep full Markup Compatibility PreserveElements/PreserveAttributes policy, XML
Signature canonicalization and digest validation, certificate-chain policy,
encrypted package policy, nested embedded-package graph expansion, and
higher-level DOCX UI treatment of compatibility diagnostics as separate
bounded slices.
