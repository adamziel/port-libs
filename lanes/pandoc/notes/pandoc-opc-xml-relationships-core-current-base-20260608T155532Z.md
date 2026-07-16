# Pandoc OPC XML Relationships Core Current Base

Session: `port-dev-pandoc-opc-relationships-20260608T155532Z`
Micro-slice: `pandoc-opc-xml-relationships-core-current-base-20260608T155532Z`
Base accepted HEAD: `86df6fefba691ff921a8e11a304488be957a19c7`

## Behavior

Added native OPC relationship source-closure inventory support in
`OpcRelationshipGraph::relationshipSourceClosureInventory()`.

The closure report starts from a selected relationship source, optionally
filtered by relationship type, and records:

- expanded relationship sources with closure depth;
- relationship sources outside the selected closure;
- stop reasons for external targets, invalid targets, missing package parts,
  relationship-part targets, cycles, and targets whose relationship source is
  not loaded;
- aggregate issue codes for importer and reviewer preflight.

The WordPress DOCX OPC preflight example now exposes the closure summary under
`relationshipSourceClosure` and `wordpressImport.relationshipClosureReview`.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline focused OPC test before the patch:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2272 assertions, 0 failures`.
- Final focused OPC test after the patch:
  `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  passed with `1 test files, 2311 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  passed with `opc docx preflight self-test ok`.

## Status Delta

- Focused OPC coverage adds 1 PHP PASS case.
- Focused OPC assertions move from 2272 to 2311, a delta of 39 assertions.
- `lane-status.json` `phpPass` moves from 1695 to 1696.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from 2115 to 2116.
- OPC relationship graph support cases move from 13 to 14.
- OPC relationship closure cases move from 3 to 4.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
OPC package support: `OpcRelationships`, `OpcRelationshipGraph`,
`OpcPackagePath`, `OpcContentTypes`, `ZipPackage`, and the existing WordPress
DOCX OPC preflight example.

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, XMLDSig validator, external XML tool, online service,
live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted OPC relationship work for content-type
inventory, relationship content-type role checks, Pack URI part-name
validation, fixed relationship part source mapping, package-signature
relationship-transform reference content-type preflight, digital-signature
role review, reserved `_rels` directory handling, or reverse reference
inventory. It only owns selected source-closure expansion and stop-policy
reporting.

## Next Task

For OPC relationships follow-up, choose a non-overlapping package semantics gap
such as relationship mode/content-type role cross-checks, signature part
policy, or DOCX/EPUB package relationship handoff.
