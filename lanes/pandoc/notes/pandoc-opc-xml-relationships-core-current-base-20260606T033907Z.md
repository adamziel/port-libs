# OPC RelationshipTransform Reference URI Kinds

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T033907Z`
Base accepted HEAD: `71a2ed72a9b0c34179d3caee1b9b9a3d99213629`

## Behavior

`OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now classifies XML Signature `RelationshipTransform` `Reference URI` values that cannot identify an OPC package relationship part before attempting package-path resolution:

- `#local` same-document references report `relationship-transform-reference-same-document` and still report `relationship-transform-reference-has-fragment`.
- `https://...` absolute references and `//...` network-path references report `relationship-transform-reference-external-uri`.
- Existing package-internal relationship part references with a fragment remain resolvable and keep the existing `relationship-transform-reference-has-fragment` diagnostic.

The WordPress DOCX OPC preflight example now exposes these unsupported URI-kind guards in `signatureReferenceUriKindGuards`.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1007 assertions, 0 failures`.
- Red-first: after adding the focused test, the same command failed with `1 test files, 1010 assertions, 1 failures` because `#local-relationship-transform` resolved to `/_xmlsignatures/sig-reference-uri-kinds.xml`.
- Green: after implementation, `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 1051 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` printed `opc docx preflight self-test ok`.

## Status Delta

- `lane-status.json` `phpPass`: `1181 -> 1182`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1631 -> 1632`.
- OPC relationship graph support cases: `11 -> 12`.
- OPC relationship graph assertions: `58 -> 102`.

## Dependency Closure

No new support component was needed. The slice reused native PHP `ZipPackage`, `XmlHtmlDom`, `OpcPackagePath`, `OpcRelationships`, and `OpcRelationshipGraph` support. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, or live provider test was executed.

## Non-Overlap

This does not repeat the prior signature `ContentType` query preflight, missing relationship part preflight, selector-shape guards, package relationship part fragment diagnostics, content-type inventory, or digital-signature origin traversal slices.

## Follow-Up

Keep XML canonicalization byte output, digest/signature verification, certificate and trust chain inspection, encrypted package handling, and full Pandoc DOCX/OPC runner parity as separate bounded slices.
