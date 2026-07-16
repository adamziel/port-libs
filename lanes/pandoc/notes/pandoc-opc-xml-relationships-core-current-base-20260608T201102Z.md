# Pandoc OPC XML Relationships 2026-06-08

## Scope

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T201102Z`

Accepted base: `70d557c28daa508cdd36e70149395d52ed3b6a44`

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Implementation Delta

`OpcRelationshipGraph::preflightDigitalSignatureSignedInfoReferences()` now
records transform positions for package-signature SignedInfo references:

- `relationshipTransformIndexes`
- `canonicalizationTransformIndexes`

For relationship-part references, the preflight now reports:

- `signed-info-relationship-transform-after-canonicalization` when a C14N
  transform appears before the OPC RelationshipTransform.
- `signed-info-multiple-relationship-transforms` when a SignedInfo reference
  declares more than one OPC RelationshipTransform.

The existing WordPress DOCX OPC preflight example now carries those transform
positions through the WordPress import summary for signature review.

## Dependency Closure

No new native PHP support component is needed. This reuses
`OpcRelationshipGraph`, `OpcContentTypes`, lane-local DOM XML parsing,
`ZipPackage` in-memory fixtures, focused `OpenPackagingConventionsTest.php`
coverage, and the existing WordPress DOCX OPC preflight example.

Pandoc execution, Cabal solver/build/test commands, Haskell runners, Word,
LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online
services, live provider tests, and live-service provider tests remain out of
scope for this lane slice.

## Non-Overlap

This slice avoids accepted OPC rows for content-type inventories, Pack URI
part-name validation, signature reference ContentType query preflight,
canonicalization profile handoff, relationship target safety, closure
traversal, relationship id validation, relationship transform selector parsing,
and digital-signature role/content-type checks. The owned behavior is only the
bounded SignedInfo transform-chain policy for OPC package relationship-part
references.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2447 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed with `1 test files, 2450 assertions, 1 failures` before the
  implementation because the transform index metadata was absent and invalid
  SignedInfo transform order was not reported.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2467 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.
- `php -l` passed for changed PHP files.
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  passed.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

Mapped denominator: `2215 -> 2216`.

OPC relationship graph support cases: `13 -> 14`.

OPC relationship graph assertions: `210 -> 230`.

Lane `phpPass` remains `1795` because this slice adds assertions inside an
existing focused OPC PASS case rather than adding a new PHP PASS line.

## Next Task

For OPC follow-up, choose a non-overlapping package-signature or
relationship-graph gap such as signature object policy diagnostics, digest
preflight/reporting, relationship target normalization, or embedded-package
closure review. Keep external validators and office/converter tooling out of
the lane.
