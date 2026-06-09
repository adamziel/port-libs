# Doctemplates Core Current Base - Malformed Separator Rejection

Slice: `pandoc-doctemplates-core-current-base-20260609T033751Z`
Base accepted HEAD: `74dfce3206dc1728f34071078950751a79a89c47`

## Source Truth

- The accepted doctemplate source-truth notes for this lane map upstream
  `doctemplates-0.11.0.1` parser behavior: bracketed separators are parsed by
  `pSep`, consuming one literal `[` payload through the required first closing
  `]`.
- This slice owns the remaining malformed payload edge where an extra closing
  bracket after the separator must not be treated as literal separator text.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word,
  LibreOffice, zip/unzip, external template engine, TeX/PDF engine, Typst,
  browser renderer, roff renderer, external validator, online service, live
  provider test, or live-service provider test was executed.

## Implementation

- `DocTemplate::parseVariableExpression()` now rejects separators whose parsed
  payload still contains `]`, covering malformed variable separators such as
  `$sources[a]b]$` and `${ sources[a]b] }`.
- `DocTemplate::parsePartialCallExpression()` now fails closed when a partial
  separator is followed by non-pipe trailing text, covering direct and applied
  partial calls such as `${ components/row()[a]b] }`.
- Valid separator payloads containing an opening bracket remain supported.
- The WordPress doctemplate review-packet smoke now checks the same malformed
  separator diagnostic on the review-sources path.

## Evidence

- Rework notes: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  files existed for this lane.
- Baseline focused command before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1124 assertions, 0 failures`.
- Red-first probe before implementation:
  `php <<'PHP' ... render('Sources: $sources[a]b]$', ['sources' => ['media', 'links']]) ... PHP`
  rendered `Sources: mediaa]blinks`.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1129 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.
- PHP lint passed for:
  `lanes/pandoc/src/DocTemplate.php`,
  `lanes/pandoc/tests/DocTemplateTest.php`, and
  `lanes/pandoc/examples/wordpress-doctemplate-review-packet.php`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added one focused PHP PASS case with five assertions.
- `lane-status.json` `phpPass`: `2238 -> 2239`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2647 -> 2648`.
- Added `mappedDoctemplateMalformedSeparatorCases: 1`.
- Added `doctemplateMalformedSeparatorAssertions: 5`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`DocTemplate` tokenization, variable/partial separator parsing, source-location
diagnostics, focused doctemplate tests, and the lane-local WordPress
doctemplate review-packet smoke.

## Non-Overlap / Follow-Up

This does not repeat accepted doctemplate comments, delimiter trimming,
unclosed separator diagnostics, valid braced or unbraced opening-bracket
separators, variable separator ordering after pipes, Unicode diagnostic
columns, variable truthiness, loops, breakable-space wrapping, parameterized
pipes, partial rebinding, recursion-limit ordering, explicit nesting,
default-template fallback, or broad filesystem loading. A useful follow-up
would be another bounded parser/resource diagnostic or doclayout wrapping edge
that does not duplicate malformed separator payload rejection.
