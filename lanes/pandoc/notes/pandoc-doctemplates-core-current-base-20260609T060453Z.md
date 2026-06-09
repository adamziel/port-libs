# Pandoc Doctemplates Core Current Base 2026-06-09T06:04:53Z

## Source Truth

- Lane: `pandoc`
- Micro-slice: `pandoc-doctemplates-core-current-base-20260609T060453Z`
- Accepted base: `11b5789183ebb8ab34ff922479caf161e9cc4881`
- Rework notes checked first:
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  returned no files.
- Upstream fixture source: doctemplates `0.11.0.1` `test/pad.test` keeps one
  final newline after the closing block-pipe table rule even when the template
  source has a redundant blank line before the fixture separator.
- Upstream implementation source: doctemplates `Internal.hs` applies block
  pipes through doclayout blocks and renders root output through doclayout.
- URLs inspected as static source truth:
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/pad.test`
  - `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Internal.hs`

No Pandoc command, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external converter,
TeX/PDF engine, browser renderer, online conversion service, live provider
test, or live-service provider test was executed.

## Behavior

`DocTemplate` now collapses one redundant trailing blank line only when the root
template source itself ends with a redundant blank line. This matches upstream
`pad.test` for block-pipe table output:

- ordinary one-final-newline root output remains unchanged;
- default-template output generated from variable content remains unchanged;
- included partial rendering remains unchanged and still strips exactly one
  included-partial final line ending;
- root templates that end in `\n\n`, `\r\n\r\n`, or equivalent redundant final
  source line endings emit one final rendered line ending when the rendered
  output already ended with a blank line.

The WordPress doctemplate review-packet smoke now checks the same reviewer-table
shape with adjacent block pipes and a redundant final source blank line.

## Evidence

- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1141 assertions, 0 failures`.
- Red-first fixture comparison before the fix:
  local native comparison against doctemplates `0.11.0.1` fixtures reported
  `pad.test FAIL expectedLen=430 actualLen=431`, with the extra byte at offset
  `430` being a second final newline.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1142 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2418 -> 2419`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2807 -> 2808`.
- Added `mappedDoctemplatePadFixtureCases: 1`.
- Added `doctemplatePadFixtureAssertions: 1`.
- Focused doctemplate assertions: `1141 -> 1142`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`DocTemplate` parsing/rendering, existing block-pipe composition, the lane-local
focused PHP test runner, and the WordPress doctemplate review-packet smoke.
Full upstream Pandoc/doctemplates runner parity remains a separate
upstream-runner dependency task requiring hydrated pinned upstream sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted doctemplate work for comments, delimiter
trimming, variable truthiness, loops, `$^$` nesting, source-aligned continuation
dedent, partial recursion, standalone partial line-ending suppression,
block-pipe width/reboxing/horizontal composition, pipe quote/separator
diagnostics, default template resources, extension-qualified resource lookup,
or XML/HTML5 DOM select metadata. It owns only upstream `pad.test` final
blank-line parity for root doctemplate output.

## Follow-Up

The remaining non-overlapping doctemplates fixture gap from the local static
comparison is full `nest.test` indentation parity inside nested conditionals and
loops. That should be handled as a separate bounded slice.
