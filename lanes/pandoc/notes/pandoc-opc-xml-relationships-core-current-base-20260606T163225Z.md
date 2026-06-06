# Pandoc OPC Relationships Current-Base Slice

Slice: `pandoc-opc-xml-relationships-core-current-base-20260606T163225Z`
Base: `9b626637adac74dd83b40dfa99de8ceeabc8d9b2`

## Behavior

This slice tightens OPC relationship part-name handling before canonical package path resolution. Raw empty path segments and dot segments in relationship part names are now rejected instead of being normalized into an existing `.rels` part.

The same guard is applied to absolute package-part `URI` values on XML Signature RelationshipTransform references. A malformed reference such as `/word/./_rels/document.xml.rels` now stays invalid, preserves the requested `SourceId` list for diagnostics, and does not materialize relationships, relationship XML, source part names, target checks, or existence checks from the normalized `/word/_rels/document.xml.rels` part.

## Source Truth

OPC relationship parts are package part names, and package names are not alternate aliases for dot-segment spellings. Pandoc DOCX/OpenXML conversion depends on selecting exact `_rels/.rels` and part-local `_rels/*.rels` package members before resolving document/media/package-signature relationships.

## Verification

Baseline:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 1183 assertions, 0 failures`.

Final:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: `1 test files, 1238 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Result: `opc docx preflight self-test ok`.

PHP lint:

`php -l lanes/pandoc/src/OpcRelationships.php`

Result: no syntax errors.

`php -l lanes/pandoc/src/OpcRelationshipGraph.php`

Result: no syntax errors.

`php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Result: no syntax errors.

`php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Result: no syntax errors.

Diff check:

`git diff --check -- lanes/pandoc`

Result: no output.

Root harness: not run - isolated micro-slice.

## Status Delta

Focused assertions: `1183 -> 1238` (`+55`).
Focused PHP PASS cases: `+1`.
`lane-status.json` `phpPass`: `1366 -> 1367`.
`UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1779 -> 1780`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`, `OpcRelationships`, `OpcRelationshipGraph`, `OpcPackagePath`, and the existing DOCX OPC WordPress preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external XML/OPC tool, XMLDSig validator, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted content-type inventory, signature ContentType query preflight, case-equivalent path lookup, relationship Id validation, target preflight, closure traversal, role content-type matching, TargetMode transform XML, or Pack URI content-type override target validation. The slice only covers raw empty/dot segment rejection for relationship part names and signature RelationshipTransform absolute reference URIs.

## Follow-Up

Keep cryptographic signature validation, XML canonicalization, digest verification, encrypted package policy, nested embedded-package graph expansion, and higher-level DOCX reader integration as separate bounded slices.
