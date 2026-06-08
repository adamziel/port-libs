# OPC XML relationships current-base invalid ContentType query slice

Date: 2026-06-08 UTC
Base accepted HEAD: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T224229Z`

## Behavior

Added bounded validation for OPC XML Signature `ds:Reference` `ContentType`
query values used with `RelationshipTransform` references.

- `OpcContentTypes::isValidContentType()` now exposes the existing OPC MIME
  grammar without changing constructor/parse exception behavior.
- `OpcRelationshipGraph::preflightSignatureRelationshipTransforms()` now
  flags decoded query values such as `ContentType=application/xml%20bad` with
  `invalid-reference-content-type-query`.
- The preflight still preserves the decoded query value, relationship part,
  actual relationship-part content type, selected relationship ids, target
  validity, and `reference-content-type-mismatch` metadata for reviewer
  diagnostics.
- The WordPress DOCX OPC preflight smoke now includes this invalid query syntax
  row alongside the existing invalid and missing relationship-part content type
  guards.

## Evidence

Rework note check:

`rg --files /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -g 'port-pandoc-*.needs-lane-rework.md'`

Only stale 20260525 pandoc rework notes existed, and none matched OPC
relationships/content-types.

Red-first focused check after adding the fixture:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Failed with `1 test files, 2712 assertions, 1 failures` because the malformed
decoded `ContentType` query only reported `reference-content-type-mismatch`.

Focused check after implementation:

`php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Passed with `1 test files, 2713 assertions, 0 failures`.

WordPress DOCX OPC example smoke:

`php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`

Passed with `opc docx preflight self-test ok`.

Changed-file syntax checks:

`php -l lanes/pandoc/src/OpcContentTypes.php`

Passed with `No syntax errors detected in lanes/pandoc/src/OpcContentTypes.php`.

`php -l lanes/pandoc/src/OpcRelationshipGraph.php`

Passed with `No syntax errors detected in lanes/pandoc/src/OpcRelationshipGraph.php`.

`php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`

Passed with `No syntax errors detected in lanes/pandoc/tests/OpenPackagingConventionsTest.php`.

`php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`

Passed with `No syntax errors detected in lanes/pandoc/examples/wordpress-docx-opc-preflight.php`.

Status JSON and lane diff checks:

`jq empty lanes/pandoc/lane-status.json`

Passed with no output.

`git diff --check -- lanes/pandoc`

Passed with no output.

## Non-overlap

This slice stays within OPC content-types/relationships XML package semantics
and package-signature relationship transform preflight. It does not repeat
recent accepted OPC work for content-type inventory grouping, Pack URI part
name validation, Markup Compatibility `AlternateContent`, SignedInfo reference
inventory, relationship transform selector materialization, or malformed
percent-escape query detection.

## Dependency Closure

No new support component is needed. The slice reuses native `OpcContentTypes`,
`OpcRelationshipGraph`, `ZipPackage`, focused `OpenPackagingConventionsTest.php`,
and the lane-local WordPress DOCX OPC preflight example.

No Pandoc, Word, LibreOffice, zip/unzip, XMLDSig validators, external XML
tools, online services, live provider tests, or live-service provider tests
were run.

## Follow-up

A useful non-overlapping OPC follow-up would target remaining package-signature
relationship transform canonicalization/source policy edges or package-wide
relationship/content-type diagnostics not already covered by content-type
inventory, Pack URI validation, `AlternateContent`, SignedInfo reference
inventory, or invalid query grammar cases.
