# Doctemplates Current-Base Rendered Empty-Line Nesting

Slice: `pandoc-doctemplates-core-current-base-20260609T012742Z`
Base accepted HEAD: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

## Source Truth

- Upstream doctemplates/doclayout behavior treats rendered `Text`/`String` nesting as indentation for non-empty follow-on lines, while the upstream changelog records the bounded parity point: empty lines are not indented.
- Static references used: https://www.stackage.org/package/doctemplates and https://hackage.haskell.org/package/doctemplates-0.11.0.1/docs/src/Text.DocTemplates.Parser.html.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate::nestMultiline()` now preserves empty lines inside already-rendered variable or partial values instead of padding those blank lines with source indentation.
- Non-empty subsequent lines still receive the same automatic or explicit caret nesting indentation.
- Source-template blank lines that are explicitly nested by template text remain on the existing `nestTemplateTextChunk()` path.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline focused command before code edit: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1096 assertions, 0 failures`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` -> `1 test files, 1098 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` -> `OK wordpress doctemplate review packet`.
- PHP lint passed for `lanes/pandoc/src/DocTemplate.php`, `lanes/pandoc/tests/DocTemplateTest.php`, and `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with two assertions.
- `lane-status.json` `phpPass`: `2037 -> 2038`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2451 -> 2452`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedDoctemplateNestingCases`: `2 -> 3`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP doctemplate renderer, focused doctemplate tests, and the lane-local WordPress doctemplate review-packet smoke.

## Non-Overlap / Follow-Up

This does not repeat explicit source blank-line nesting, comments, delimiter trimming, variable truthiness, loops, breakable-space wrapping, pipes, partial rebinding, default-template fallback, or braced separator parsing. A useful follow-up would be remaining doctemplate partial path diagnostics or doclayout wrapping edge cases.
