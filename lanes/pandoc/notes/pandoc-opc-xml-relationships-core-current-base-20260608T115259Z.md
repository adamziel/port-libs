# Pandoc OPC XML Relationships Current-Base Slice

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T115259Z`

Accepted base: `ef204610238d00e07d53becb139e28941de74b31`

## Behavior

Added native OPC package-signature preflight coverage for RelationshipTransform references to `.rels` parts whose package content type is not usable as an OPC relationship part:

- `reference-relationship-content-type-invalid` when the referenced `.rels` part is declared with a non-relationship content type such as `application/xml`.
- `reference-relationship-content-type-missing` when the referenced `.rels` part exists in the package but has no content type declaration.

The transform row still reports the existing `reference-content-type-mismatch`, `relationship-source-not-loaded`, and unmatched selector diagnostics, so WordPress/DOCX import review packets can distinguish a missing relationship part from a present but untrusted relationship part.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2158 assertions, 0 failures`.
- Red-first: focused OPC test failed before implementation with `1 test files, 2172 assertions, 1 failures`; the new invalid content-type issue was absent.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php` -> `1 test files, 2187 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test` -> `opc docx preflight self-test ok`.
- PHP lint passed for `lanes/pandoc/src/OpcRelationshipGraph.php`, `lanes/pandoc/tests/OpenPackagingConventionsTest.php`, and `lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.

Delta: +1 focused PASS case and +29 focused assertions.

## Non-Overlap

This does not repeat the already accepted OPC content-type inventory, signature reference `ContentType` query matching, Pack URI validation, reserved `_rels` directory checks, direct relationship-loader content-type guard, package-root external-target handling, or relationship type policy slices. This slice is specifically about signature RelationshipTransform references to present `.rels` package parts whose package content type is invalid or missing.

## Dependency Closure

No new support component is needed. The slice reuses `ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`, existing XML signature transform parsing, and the WordPress DOCX OPC preflight example. No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, Cabal/Haskell runner, online service, live provider test, or live-service provider test was run.

## Follow-Up

A non-overlapping OPC follow-up could cover signed part digest-source inventory, signature origin package-role aggregation, or relationship transform canonicalization diagnostics.
