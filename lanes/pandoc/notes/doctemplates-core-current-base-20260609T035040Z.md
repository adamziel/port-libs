# Doctemplates Core Current Base - Nested Breakable-Space Wrapping

Slice: `pandoc-doctemplates-core-current-base-20260609T035040Z`
Base accepted HEAD: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Source Truth

- Upstream `doctemplates-0.11.0.1` renders templates through `Text.DocTemplates.Internal.renderTemp`, which tracks the current column, applies `DL.nest` for `Nested` templates, and uses `Text.DocLayout` for breakable-space wrapping.
- Static primary sources used:
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Internal.hs`
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`
  - `https://hackage-content.haskell.org/package/doclayout-0.5.0.1/docs/Text-DocLayout.html`
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate` now annotates breakable-space markers rendered under an active explicit `$^$` nesting column and uses that continuation indentation when `renderWrapped()` breaks a line.
- The marker cleanup paths used by `nowrap`, `chomp`, and block pipes now remove annotated markers so internal metadata cannot leak into rendered output.
- Added a focused doctemplate test for inline nested reviewer text and nested partial output.
- Updated the WordPress doctemplate review-packet self-test with the same wrapped inline reviewer note shape.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1129 assertions, 0 failures`.
- Red-first probe before implementation:
  `php -r 'require "tools/bootstrap.php"; $r = new PortLibs\Pandoc\DocTemplate(); echo str_replace("\n", "\\n\n", $r->renderWrapped("<p>\$^\$Note: \$~\$media links layout status\$~\$</p>", [], 18));'`
  rendered `<p>Note: media\nlinks layout\nstatus</p>`, losing the active explicit nesting column on wrapped continuation lines.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1131 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for:
  `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with two assertions.
- `lane-status.json` `phpPass`: `2254 -> 2255`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2660 -> 2661`.
- Added `mappedDoctemplateNestedWrappingCases: 1`.
- Added `doctemplateNestedWrappingAssertions: 2`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`DocTemplate` tokenizer, explicit nesting state, breakable-space wrapper,
focused doctemplate tests, and the lane-local WordPress doctemplate review
packet smoke. Full upstream doctemplates runner parity remains a separate
upstream-runner dependency task requiring a hydrated checkout and Haskell test
executables.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
unclosed or malformed separator diagnostics, valid braced/unbraced separator
payload scanning, variable separator ordering after pipes, Unicode diagnostic
columns, variable truthiness, loops, parameterized pipes, partial rebinding,
recursion-limit ordering, explicit nesting blank-line behavior, applied-partial
newline preservation, default-template fallback, extension-qualified partial
aliases, or broad filesystem loading. A useful follow-up would be another
bounded parser/resource diagnostic or default-resource gap.
