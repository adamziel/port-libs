# OPC XML relationships current-base AlternateContent slice

Date: 2026-06-08 UTC
Base accepted HEAD: `0091df3813ad73254e2c1f230ab975292c14a7c0`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T210155Z`

## Behavior

Added bounded OPC Markup Compatibility `mc:AlternateContent` support for package XML records in `[Content_Types].xml` and `.rels` files.

- Selects the first `mc:Choice` whose `Requires` prefixes are declared and map to the active package namespace.
- Falls back to `mc:Fallback` when no supported choice exists.
- Recursively parses selected branch children through the existing package child filtering.
- Rejects malformed branches: missing `Requires`, undeclared required prefixes, `Choice` after `Fallback`, unsupported choices without fallback, unexpected attributes, unexpected child elements, and non-whitespace text.

## Evidence

Red-first focused check:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Failed before implementation with `1 test files, 2569 assertions, 1 failures` because `mc:AlternateContent` was treated as an unsupported package namespace child.

Focused check after implementation:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Passed with `1 test files, 2586 assertions, 0 failures`.

WordPress DOCX OPC example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Passed with `opc docx preflight self-test ok`.

Changed-file syntax checks:

`php -l lanes/pandoc/src/OpcMarkupCompatibility.php && php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php && php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Passed with no syntax errors.

Status JSON and lane diff checks:

`jq empty lanes/pandoc/lane-status.json && git diff --check -- lanes/pandoc`

Passed with no output.

## Non-overlap

This slice stays within OPC content-types and relationships XML package semantics. It does not repeat recent accepted OPC work for content-type inventory grouping, signature relationship reference `ContentType` query preflight, Pack URI part-name validation, or nested embedded-package relationship closure.

## Dependency Closure

No new support component is needed. The slice reuses native `OpcContentTypes`, `OpcRelationships`, `OpcMarkupCompatibility`, `OpcRelationshipGraph`, `ZipPackage`, focused `OpenPackagingConventionsTest.php`, and the lane-local WordPress DOCX OPC preflight example.

No Pandoc, Word, LibreOffice, zip/unzip, external XML validators, XMLDSig validators, online services, live provider tests, or live-service provider tests were run.

## Follow-up

A useful non-overlapping OPC follow-up would target relationship transform canonicalization edge cases, stricter signature package source policies, or remaining package-wide content-type/relationship graph diagnostics not already covered.
