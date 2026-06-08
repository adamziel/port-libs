# Pandoc OPC Relationships Current-Base Slice

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T123430Z`
Base accepted HEAD: `20c5b7a659021f3c9cfccfc7e0167c7b0daef7ae`

## Scope

Implemented bounded native PHP OPC content-type media-type matching for relationship/package role checks. OPC `ContentType` records may carry MIME parameters, and the lane already preserves those producer strings. This slice makes role matching compare the media type case-insensitively while still returning the exact stored `ContentType` value in review/preflight payloads.

Covered paths:

- relationship part loading from package content types;
- direct source relationship loading through `OpcRelationships::fromPackage()`;
- office document root, core properties, and digital signature role preflight;
- package-signature RelationshipTransform `ContentType` query checks;
- WordPress DOCX OPC preflight smoke coverage.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2187 assertions, 0 failures`.
- Red-first: after adding the new parameterized-media-type case, the same focused command failed with `1 test files, 2190 assertions, 1 failures` because `/word/_rels/document.xml.rels` with `application/vnd.openxmlformats-package.relationships+xml; charset=UTF-8` was not loaded as a relationship part.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` passed with `1 test files, 2212 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` passed with `opc docx preflight self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses existing `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, focused OPC tests, and the WordPress DOCX OPC preflight example. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted case-insensitive content-type role slice, relationship `Type` URI policy slice, target URI preflight slice, reserved relationship content-type slice, or signature invalid/missing relationship-content-type guard slices. The new behavior is specifically parameterized MIME media-type matching with exact producer string preservation.
