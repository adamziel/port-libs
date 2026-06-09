# Doctemplates Core Current Base - Applied Partial Newline Preservation

Slice: `pandoc-doctemplates-core-current-base-20260609T030236Z`
Base accepted HEAD: `229b80984669c571fd654cf306ec726e5c0ff753`

## Source Truth

- Upstream `doctemplates-0.11.0.1` fixture `test/pipes.test` applies
  `$items/pairs/reverse:enum()$` to `test/enum.txt`.
- Upstream `test/enum.txt` ends with two newlines; `pPartial` removes exactly
  one final newline from the partial source before compiling it, so one newline
  survives between rendered applied-partial items.
- Static primary sources used:
  `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`,
  `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Internal.hs`,
  `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/pipes.test`,
  and `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/enum.txt`.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser
  renderer, online converter, live provider test, or live-service provider test
  was executed.

## Implementation

- `DocTemplate::nestTemplateTextChunk()` now detects terminal blank line-ending
  runs while explicit nesting is active and preserves those line endings without
  injecting indentation spaces.
- This keeps upstream applied partial output such as `pairs/reverse:enum()` from
  starting the next rendered partial on a stray indented line after the previous
  partial preserved one final newline.
- Existing internal source-aligned blank lines inside explicit nesting remain on
  the prior indentation path.
- The WordPress doctemplate review-packet smoke now renders a reversed
  source-enumeration partial with two final newlines to verify reviewer packets
  keep list items separated without accidental indentation.

## Evidence

- Rework notes: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  files existed for this lane.
- Red-first focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  failed with `1 test files, 1119 assertions, 1 failures`; the new upstream
  fixture assertion rendered `B.  two` and `A.  one...` after stray spaces from
  the previous explicit-nesting block.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1123 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with five assertions.
- `lane-status.json` `phpPass`: `2197 -> 2198`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2610 -> 2611`.
- Added `mappedDoctemplateAppliedPartialNewlineCases: 1`.
- Added `doctemplateAppliedPartialNewlineAssertions: 5`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`DocTemplate` tokenizer, explicit-nesting renderer, applied partial renderer,
focused doctemplate tests, and the lane-local WordPress doctemplate review
packet smoke.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
unbraced or braced separator scanning, variable separator ordering, Unicode
diagnostic columns, variable truthiness, breakable-space wrapping,
parameterized pipes, default-template fallback, partial rebinding,
recursion-limit ordering, or ODF/OpenDocument field work. A useful follow-up
would be another bounded upstream doctemplates fixture gap or doclayout
wrapping edge that does not reuse this terminal explicit-nesting newline path.
