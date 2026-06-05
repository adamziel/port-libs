# Pandoc OPC XML Relationships Core Current Base

Slice: `pandoc-opc-xml-relationships-core-current-base-20260605T020704Z`
Base accepted HEAD: `542dde5fff8943ee8938b6e2d7f5e57b947893fe`

## Behavior

- Added bounded OPC relationship `Type` URI preflight in native PHP.
- `OpcRelationship::relationshipTypePreflight()` classifies relationship type
  values as absolute URI, relative reference, network-path reference, or
  fragment reference.
- Relationship target preflight and reachable closure now carry
  `relationshipTypeKind`, `relationshipTypeScheme`, `relationshipTypeValid`,
  and `relationshipTypeIssues` for every relationship.
- Non-absolute relationship types and whitespace/control-bearing URI bytes are
  reported as invalid relationship diagnostics without discarding the whole
  package graph, so WordPress import review can show malformed package metadata
  beside unsafe external targets and missing parts.
- Updated the WordPress DOCX OPC preflight example to expose a malformed
  relationship Type value in the external relationship review queue.

## Source Truth

- OPC relationships identify semantics through the relationship `Type` URI,
  including the root `officeDocument` relationship consumed by Pandoc-style DOCX
  loading and the package digital-signature relationship types already mapped in
  this lane.
- This slice keeps invalid type values as preflight diagnostics instead of a
  parse-time hard failure so review tools can still audit the rest of a DOCX
  package when a malformed relationship appears.

## Evidence

- Baseline focused OPC test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result before new tests: `1 test files, 322 assertions, 0 failures`.
- Red-first focused OPC test after adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 322 assertions, 1 failures`; missing
    `OpcRelationship::relationshipTypePreflight()`.
- Focused OPC rerun after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 343 assertions, 0 failures`.
- PHP syntax checks:
  - `php -l lanes/pandoc/src/OpcRelationship.php`
  - `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
  - `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-opc-preflight.php`
  - Result: no syntax errors.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`.
- Lane JSON validation:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Full focused lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5571 assertions, 0 failures`.
- Diff whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Delta

- Focused OPC tests moved from 27 to 28 PASS cases.
- Focused OPC assertions moved from 322 to 343, adding 21 assertions.
- Lane status moved from 524 to 525 PHP PASS lines.
- Manifest mapped checks moved from 999 to 1,000 with a new
  `opcRelationshipTypePreflightCases` bucket.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage`, `OpcContentTypes`, `OpcRelationships`, `OpcRelationshipGraph`,
and `OpcPackagePath` primitives. No Pandoc, Word, LibreOffice, zip/unzip,
Haskell runner, browser renderer, or online service is needed for this slice.

## Non-Overlap

This is additive on top of accepted ZIP/OPC package primitives, content-type
lookup and MIME grammar validation, relationship XML parsing, XML NCName Id
validation, URI target decoding, target integrity preflight,
relationship-part source validation, external target policy, package-part
preflight, reachable relationship closure traversal, and digital-signature
relationship preflight.

## Follow-Up

Keep embedded package policy, external relative-reference rewrite policy,
encrypted package policy, cryptographic signature verification, signature
transform/canonicalization parsing, full MIME parameter normalization, and
higher-level DocxReader UI treatment of OPC diagnostics as separate bounded
slices.
