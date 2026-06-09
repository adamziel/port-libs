# OPC XML Relationships Current-Base External Target Percent Preflight

Slice: `pandoc-opc-xml-relationships-core-current-base-20260609T031737Z`
Base accepted HEAD: `fcee36bd5dbe5864d3125594c593630bcda502b2`

## Behavior

- Tightened native OPC external relationship target preflight so `TargetMode="External"` targets reject malformed percent escapes such as `%ZZ`.
- Added rejection for percent-encoded control bytes such as `%00` and `%7F`, while preserving ordinary encoded spaces such as `%20`.
- Propagated the new issues through `OpcRelationshipGraph::preflightTargetsForSource()` and reachable-closure review rows.
- Extended the WordPress DOCX OPC preflight smoke with a compact `externalTargetPercentGuards` review packet so import queues can surface unsafe external-link targets without invoking external validators.

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first focused command after adding the test:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3034 assertions, 1 failures`
  - Failure: external target `%ZZ` was incorrectly allowed.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - Result: `1 test files, 3050 assertions, 0 failures`
  - Delta from prior accepted focused OPC run: `+37` focused assertions and one lane PASS case.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-opc-preflight.php --self-test`
  - Result: `opc docx preflight self-test ok`
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OpcRelationship`, `OpcRelationshipGraph`, `ZipPackage` fixtures, the focused OPC test harness, and the existing WordPress DOCX OPC preflight example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, XMLDSig validator, browser renderer, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted internal target percent/path validation, external raw-whitespace guards, network-path/base-URI policy, unsafe-scheme policy, relationship target integrity preflight, relationship part load summaries, Pack URI validation, signature relationship-transform reference URI checks, encrypted package policy, embedded package policy, relationship closure traversal, or XML/HTML5 DOM work. It only owns external relationship target percent-escape diagnostics for OPC package review.

## Follow-Up

- Consider threading the compact external-target percent guard into higher-level DOCX import reports beside hyperlink and media relationship diagnostics.
- A next OPC slice should choose a distinct package relationship policy or signature metadata edge and avoid repeating external target URI percent diagnostics.
