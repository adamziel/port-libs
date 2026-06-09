# Doctemplates Core Current Base - Unclosed Separator Diagnostics

Slice: `pandoc-doctemplates-core-current-base-20260609T023018Z`
Base accepted HEAD: `a90c290373fc105bb0c871a8045e20501401691f`

## Source Truth

- Upstream `doctemplates-0.11.0.1` parses bracketed separators with `pSep`,
  consuming a literal `[` payload through the required closing `]`.
- Upstream `compileTemplate` returns parser diagnostics with the template path
  as source context.
- Static primary source used:
  `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`
- No Pandoc executable, Cabal solver/build/test command, Haskell runner,
  Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine,
  browser renderer, online converter, live provider test, or live-service
  provider test was executed.

## Implementation

- `DocTemplate` now tracks the byte offset of an opened bracketed separator
  while scanning `$...$` and `${...}` directives.
- When the matching `]` is absent, the renderer raises
  `Unclosed doctemplate separator` at the bracket location instead of a generic
  unclosed directive at the opening delimiter.
- The diagnostic covers variable separators, direct partial separators,
  applied partial separators, and resource-backed template source paths.
- The WordPress doctemplate review-packet self-test now checks the same
  malformed applied-partial separator diagnostic for reviewer-list templates.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1108 assertions, 0 failures`.
- Red-first probe before implementation:
  `php <<'PHP' ... render('$reviewSources[ / $', ['reviewSources' => ['media']]) ... PHP`
  reported `Unclosed doctemplate $...$ directive at <template>:1:1`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1112 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with four assertions.
- `lane-status.json` `phpPass`: `2153 -> 2154`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2577 -> 2578`.
- Added `mappedDoctemplateUnclosedSeparatorDiagnosticCases: 1`.
- Added `doctemplateUnclosedSeparatorDiagnosticAssertions: 4`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`DocTemplate` tokenizer, source-location diagnostics, focused doctemplate
tests, and the lane-local WordPress doctemplate review-packet smoke.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
unbraced dollar separator scanning, braced separator payload scanning,
variable truthiness, loops, breakable-space wrapping, parameterized pipes,
partial rebinding, recursion-limit ordering, explicit nesting, default-template
fallbacks, or broad filesystem loading. A useful follow-up would be another
bounded parser diagnostic or doclayout wrapping edge that does not duplicate
this unclosed bracketed-separator path.
