# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T183448Z`
Base accepted HEAD: `491fa94b2ad9759bb28ac262b0ad00542377c4c9`

## Behavior

- `OpcRelationships::fromPackage()` now locates relationship package entries by
  the source part represented by each `.rels` part, not only by one exact
  serialized relationship-part URI.
- `OpcRelationships::packageHasRelationshipsForSource()` uses the same
  source-equivalent scan, so direct package callers see case-equivalent source
  relationship parts and raw-space `.rels` aliases that map to the requested
  source.
- Direct loads reject multiple `.rels` package entries that represent the same
  source part, matching the existing graph-level duplicate-source safety policy
  instead of silently choosing one relationship set.
- The WordPress DOCX OPC preflight smoke now exposes direct lower-case source
  lookup for a mixed-case relationship part before handing review packets to
  graph-level relationship diagnostics.

## Source Truth

OPC relationship parts identify the source package part through their `_rels`
part name. The package already treats part-name equivalence as case-insensitive
and decodes relationship-part source aliases during graph preflight. This slice
extends that bounded package contract to the lower-level direct relationship
loader without changing relationship target graph traversal or content-type
validation.

This ports package semantics only. It does not implement XML Signature
canonicalization, digest validation, certificate policy, encrypted package
handling, DOCX body parsing, or Pandoc runner parity.

## Verification

- Baseline focused OPC run before adding the expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 907 assertions, 0 failures`.
- Red-first focused OPC run after adding the source-equivalent package-loader
  expectation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 908 assertions, 1 failures`.
  - Failure: `packageHasRelationshipsForSource()` did not find the
    case-equivalent relationship part.
- Focused OPC run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 915 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused OPC tests moved from `55` to `56` PASS cases.
- Focused OPC assertions moved from `907` to `915`, adding `8` assertions.
- Lane `phpPass` moved from `1036` to `1037`.
- Manifest mapped native inventory moved from `1488` to `1489`.
- Added manifest counters:
  - `opcRelationshipSourceEquivalentPackageLoadCases = 1`
  - `mappedOpcRelationshipSourceEquivalentPackageLoadCases = 1`
  - `opcRelationshipSourceEquivalentPackageLoadAssertions = 8`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcRelationships`, `OpcPackagePath`, and the WordPress DOCX OPC preflight
example.

No Pandoc, Word, LibreOffice, zip/unzip, ZipArchive, XMLDSig validator, Cabal
solver/build/test command, Haskell runner, external office tool, online
sanitizer, or online service was executed.

## Non-Overlap

This does not repeat accepted OPC content-type parsing, relationship Id
validation, target integrity preflight, relationship-part source validation,
duplicate-source graph preflight, case-equivalent graph target resolution,
digital-signature relationship transforms, signature reference ContentType
query preflight, `mc:ProcessContent`, content-type inventory grouping, or
TargetMode materialization.

The slice owns only direct package relationship loading by represented source
part equivalence.

## Follow-Up

Keep cryptographic XML Signature C14N/digest validation, encrypted package
policy, nested embedded-package graph expansion, and higher-level DOCX UI
treatment of relationship diagnostics as separate bounded slices. Full Pandoc
runner parity remains gated on hydrating the upstream Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and building the Haskell Tasty
runner closure.
