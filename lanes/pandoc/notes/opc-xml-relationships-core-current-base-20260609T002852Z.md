# OPC Relationships Current-Base Relationship-Transform Payload Fingerprints

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T002852Z`
Base accepted HEAD: `28428232606f6fb6b3df81661dee1f40b90244b3`

## Behavior

- Extended `OpcRelationshipGraph::materializeRelationshipTransform()` to expose `relationshipXmlBytes` and `relationshipXmlSha256` for the canonical OPC relationship-transform XML payload.
- Propagated the same payload metadata through `preflightSignatureRelationshipTransforms()` rows so signature-reference review can compare transform payloads without invoking XMLDSig tooling.
- Updated the WordPress DOCX OPC preflight example to surface byte/hash metadata wherever it emits relationship-transform XML summaries.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2863 assertions, 0 failures`
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2880 assertions, 0 failures`
  - Delta: `+17` focused assertions and one lane PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- PHP lint: changed PHP files passed `php -l`.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` parsed with `JSON_THROW_ON_ERROR`.
- Diff whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP OPC relationship graph, existing XML/DOM helpers, `ZipPackage` fixtures, focused PHP tests, and the existing WordPress DOCX OPC preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted OPC content-type inventory, Pack URI validation, relationship target preflight, nested `_rels` payload-segment rejection, relationship-transform selector grammar, relationship-transform content-type query, package-object manifest cross-check, package-signature object policy, encrypted package policy, embedded package policy, or digest algorithm/value policy slices.

## Follow-Up

- If package signature canonicalization or digest verification is later added, consume `relationshipXmlSha256` as review provenance only until a native canonical XML comparison path exists.
- A next OPC slice could thread relationship-transform payload metadata into package signature manifest-reference summaries without changing the bounded non-validator stance.
