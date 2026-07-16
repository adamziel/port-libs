# OPC Relationships Current-Base Relationship-Part Load Summary

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T025145Z`
Base accepted HEAD: `cb8eb4f51cf712622b553d57173410c449f7e04d`

## Behavior

- Added `OpcRelationshipGraph::relationshipPartLoadSummary()` to aggregate existing OPC relationship part preflight rows before graph construction.
- The summary reports loaded/skipped relationship part counts, valid/invalid counts, total loaded relationship records, loaded/skipped part names, loaded/skipped relationship sources, load action counts, load reason counts, issue counts, and part-name buckets by reason and issue.
- Updated the WordPress DOCX OPC preflight example to expose the summary at the top level and as a compact `wordpressImport.relationshipPartLoadSummary` review packet.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 2982 assertions, 0 failures`
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3013 assertions, 0 failures`
  - Delta: `+31` focused assertions and one lane PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- PHP lint: changed PHP files passed `php -l`.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` parsed with `jq empty`.
- Diff whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP OPC relationship graph, existing relationship-part preflight rows, `ZipPackage` fixtures, focused PHP tests, and the existing WordPress DOCX OPC preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted OPC content-type inventory, Pack URI validation, relationship target preflight, nested `_rels` payload-segment rejection, relationship-transform selector grammar, relationship-transform content-type query, package-object manifest cross-check, package-signature object policy, encrypted package policy, embedded package policy, relationship closure traversal, or digest algorithm/value policy slices.

## Follow-Up

- Thread `relationshipPartLoadSummary` into the higher-level DOCX import report if the integrator wants load/reject counts beside body/properties/media extraction.
- A next OPC slice could add a non-overlapping package-signature or relationship policy edge while continuing to avoid external validators and office tools.
