# Pandoc Doctemplates Core Current Base

- Micro-slice: `pandoc-doctemplates-core-current-base-20260609T003218Z`
- Base accepted HEAD: `28428232606f6fb6b3df81661dee1f40b90244b3`
- Scope: native PHP `DocTemplate` explicit nesting support only.

## Source Truth

- Upstream doctemplates parser source: `Text.DocTemplates.Parser` `pNested` and blank-line handling in `pTemplate`.
- Reference: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs
- The upstream parser keeps blank lines as template items while nested parsing remains active. This slice maps that bounded behavior by preserving source-aligned blank lines inside an explicit `$^$` nesting region with the same rendered indentation as adjacent nested lines.

## Implementation

- `DocTemplate::nestTemplateTextChunk()` now emits the active explicit nesting indent before a blank source line that remains inside the nested region.
- Added a focused doctemplate test for source-aligned blank lines between multiline variables and source-aligned literal text.
- Updated the WordPress doctemplate review-packet self-test to cover the same blank-line nesting path.

## Evidence

- Rework notes: no current `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1093 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` failed with `1 test files, 1094 assertions, 1 failures`; the new blank nested line was emitted empty instead of with explicit nesting spaces.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed with `1 test files, 1094 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed with `OK wordpress doctemplate review packet`.

## Status Delta

- `lane-status.json` `phpPass`: `2009 -> 2010`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2425 -> 2426`.
- `mappedDoctemplateNestingCases`: `2 -> 3`.
- Added `doctemplateExplicitBlankLineNestingAssertions`: `1`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP doctemplate parser/renderer and Unicode display-column indentation helpers. No Pandoc, Cabal solver/build/test command, Haskell runner, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This does not repeat accepted doctemplate comments, delimiter trimming, comments whitespace, variable truthiness, loop separators, breakable spaces, parameterized pipes, partial rebinding, resource/default-template fallback, or default template resource work. It covers only explicit `$^$` source-aligned blank-line nesting.
