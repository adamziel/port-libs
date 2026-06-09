# Pandoc Doctemplates Core Current Base 2026-06-09T06:25:40Z

## Source Truth

- Lane: `pandoc`
- Micro-slice: `pandoc-doctemplates-core-current-base-20260609T062540Z`
- Accepted base: `fc8eeee0d58103faabecc24a17572b78d812884d`
- Rework notes checked first:
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  returned no files.
- Upstream fixture source: doctemplates `0.11.0.1` `test/nest.test` covers
  repeated multiline scalar interpolation, explicit `$^$` nesting, literal
  prefixes before nested multiline values, loop directives dedenting out of
  active nesting, and nested conditional interpolation.
- URL inspected as static source truth:
  `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/test/nest.test`

No Pandoc command, Cabal solver/build/test command, Haskell runner, Word,
LibreOffice, zip/unzip, external template engine, external converter,
TeX/PDF engine, browser renderer, online conversion service, live provider
test, or live-service provider test was executed.

## Behavior

The current native `DocTemplate` renderer already matched the complete upstream
`nest.test` fixture shape on this base. This patch makes that upstream behavior
countable with a focused PHP test and mirrors it in the WordPress doctemplate
review-packet self-test:

- repeated multiline scalar interpolation stays unindented until explicit
  nesting is active;
- `$^$` preserves the active explicit nesting column for multiline
  continuations;
- a literal prefix before a nested multiline value controls continuation
  indentation;
- a dedented `$for(...)$` directive exits the active nesting region before
  rendering loop content;
- nested `$if(...)$` blocks inside and after the loop keep the upstream
  dedent behavior.

## Evidence

- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1142 assertions, 0 failures`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1143 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP syntax checks:
  `php -l lanes/pandoc/tests/DocTemplateTest.php` and
  `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`
  both reported no syntax errors.
- Diff whitespace:
  `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2443 -> 2444`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2831 -> 2832`.
- `mappedDoctemplateNestingCases`: `2 -> 3`.
- Added `mappedDoctemplateFullNestFixtureCases: 1`.
- Added `doctemplateFullNestFixtureAssertions: 1`.
- Focused doctemplate assertions: `1142 -> 1143`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `DocTemplate`
parsing/rendering, existing explicit nesting and loop/conditional handling, the
lane-local focused PHP test runner, and the WordPress doctemplate review-packet
self-test. Full upstream Pandoc/doctemplates runner parity remains a separate
upstream-runner dependency task requiring hydrated pinned upstream sources and
Haskell test executables.

## Non-Overlap

This does not repeat accepted doctemplate work for comments, delimiter
trimming, variable truthiness, loops, partial recursion, standalone partial
line-ending suppression, source-aligned continuation dedent subcases,
block-pipe width/reboxing/horizontal composition, pipe quote/separator
diagnostics, default template resources, extension-qualified resource lookup,
or `pad.test` final blank-line parity. It owns only explicit focused coverage
for the complete upstream `nest.test` indentation fixture.

## Follow-Up

The next doctemplates slice should choose a non-overlapping fixture or parser
gap such as parameterized partial edge coverage, source-location diagnostics
not already covered, or additional static upstream fixture parity. Keep the
lane native PHP only and do not invoke Pandoc, Cabal/Haskell runners, external
template engines, office tools, zip/unzip, TeX/PDF engines, browser renderers,
or online services.
