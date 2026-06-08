# Pandoc OPC XML Relationships 2026-06-08

## Scope

Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T193932Z`

Accepted base: `ad5b11a3802902acf51a50b9d763682c8110442c`

No `port-pandoc-*.needs-lane-rework.md` note was present under
`/home/claude/port-libs/.tmux-team/tmp/handoff-candidates` for this lane.

## Implementation Delta

`OpcRelationshipGraph` now exposes bounded XMLDSig canonicalization profile
metadata for OPC package-signature relationship transforms:

- `canonicalizationTransforms` on SignedInfo references.
- `relationshipTransformFollowingCanonicalization` on SignedInfo relationship
  transform references.
- `followingCanonicalization` on signature relationship-transform preflight
  rows.

The native metadata maps the recognized C14N 1.0, exclusive C14N 1.0, and
C14N 1.1 algorithms, including `#WithComments` variants, into reviewer fields:
`profile`, `version`, `exclusive`, and `withComments`.

## WordPress Smoke

`wordpress-docx-opc-preflight.php` now carries the canonicalization metadata
through the WordPress import summary for DOCX signature review. Its self-test
also reflects the current closure summary for network-path external targets,
which the example already emitted before this slice.

## Dependency Closure

No new native PHP support component is needed. This reuses
`OpcRelationshipGraph`, lane-local DOM XML parsing, `ZipPackage` in-memory
fixtures, `OpenPackagingConventionsTest.php`, and the existing WordPress DOCX
OPC preflight example.

Full Pandoc execution, Cabal solver/build/test planning, Haskell runners, Word,
LibreOffice, zip/unzip, XMLDSig validators, external XML tools, online
services, live provider tests, and live-service provider tests remain out of
scope for this lane slice.

## Non-Overlap

This slice avoids accepted OPC rows for content-type inventories, Pack URI
part-name validation, signature reference ContentType query preflight,
relationship target safety, closure traversal, relationship id validation,
relationship transform selector parsing, and digital-signature role/content-type
checks. The owned behavior is only the bounded C14N profile handoff for package
signature relationship transforms.

## Verification

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2381 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  failed with `1 test files, 2307 assertions, 3 failures` before the
  implementation because the new metadata keys were absent.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2418 assertions, 0 failures`.

- `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

Root harness: not run - isolated micro-slice.

## Next Task

For OPC follow-up, choose a non-overlapping package-signature or
relationship-graph gap such as signature object policy diagnostics, transform
digest preflight, or relationship target normalization. Keep external
validators and office/converter tooling out of the lane.
