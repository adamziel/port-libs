# OPC XML Relationships Current-Base: Signature Manifest Transform Target Cross-Check

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T232708Z`
- Accepted base: `98e8999bf9b8bc75393d3cdf7374793f03cbce9c`

## Source Truth

This slice stays inside the bounded native OPC/XMLDSig support layer. It reuses the lane's existing OPC relationship graph, XMLDSig `RelationshipTransform` selector preflight, relationship-target content-type lookup, and package-object manifest reference metadata. No Pandoc, Word, LibreOffice, `zip`/`unzip`, XMLDSig validator, external XML tool, Cabal solver/build/test command, Haskell runner, online service, live provider test, or live-service provider test was executed.

## Behavior Added

`OpcRelationshipGraph::preflightDigitalSignatureMetadata()` now annotates each `ds:Object` manifest `ds:Reference` with same-signature `RelationshipTransform` target evidence:

- `relationshipTransformTargetMatched`
- `relationshipTransformTargetMatchCount`
- `relationshipTransformTargetMatches`

Each match records the signature reference index, relationship part name, source part, relationship id/type/target, selector mode (`SourceId` vs `SourceType`), relationship validity, and transform validity. This is review metadata only: unmatched manifest references still stay valid when their existing target, content-type, digest-method, and digest-value checks pass.

## Verification

Baseline before edits:

```bash
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
```

Result: `1 test files, 2755 assertions, 0 failures`.

Final focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php
```

Result: `1 test files, 2792 assertions, 0 failures`.

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test
```

Result: `opc docx preflight self-test ok`.

Syntax checks passed for:

- `lanes/pandoc/src/OpcRelationshipGraph.php`
- `lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Diff check:

```bash
git diff --check -- lanes/pandoc
```

Result: passed with no output.

## Dependency Closure

No new support component is needed. The slice reuses native PHP OPC package primitives, content-type lookup, relationship graph preflight, XML DOM parsing, and existing WordPress DOCX OPC preflight example coverage.

## Next

A non-overlapping OPC follow-up can cover additional XMLDSig package-object policy metadata, relationship-transform canonicalization handoff evidence, or DOCX/OpenXML reader package-consistency wiring. Keep the lane bounded to native PHP support-library behavior.
