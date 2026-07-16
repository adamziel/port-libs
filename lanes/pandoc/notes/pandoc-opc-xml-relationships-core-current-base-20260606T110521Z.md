# Pandoc OPC Fixed Content Types Item Preflight

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T110521Z`
Base: `6e1c2ccf2c17dca7dfe3543ea597a270de5896cf`

## Behavior

`OpcRelationshipGraph::preflightPackageConsistency()` now treats
`/[Content_Types].xml` as the fixed OPC package metadata item rather than an
ordinary package part:

- content-type overrides for `/[Content_Types].xml` are invalid and report
  `content-types-override-target`;
- internal relationship targets resolving to `/[Content_Types].xml` are
  invalid and report `targets-content-types-item`.

The graph still resolves the target and content type so WordPress/DOCX import
review packets can explain the package-shape problem without hiding the source
relationship or override.

## Evidence

- Baseline focused test before edits:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 1143 assertions, 0 failures`
- Red-first focused run after adding the expected fixed-item diagnostic:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 1146 assertions, 1 failures`
- Green focused run after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `1 test files, 1164 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - `opc docx preflight self-test ok`

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`,
`OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, and
`OpcPackagePath`. No Pandoc, Cabal solver/build/test command, Haskell runner,
Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online
service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat recent OPC relationship work for content-type inventory
grouping, package-signature relationship-transform `ContentType` query
preflight, case-equivalent path lookup, relationship Id validation, target URI
policy preflight, target-mode diagnostics, relationship-part source/orphan load
decisions, digital-signature traversal, or package closure traversal. It is
limited to fixed package metadata item exclusion in consistency preflight.

## Follow-Up

Keep fuller ISO/IEC 29500 package-conformance matrices, XML Signature
digest/canonicalization validation, and full Pandoc DOCX/EPUB/ODT runner parity
as separate bounded slices.
