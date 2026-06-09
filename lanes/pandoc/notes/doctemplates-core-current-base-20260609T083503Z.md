# Doctemplates Core Current Base - Applied Partial Separator Diagnostics

Slice: `pandoc-doctemplates-core-current-base-20260609T083503Z`

Base: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Source Truth

- Upstream `doctemplates-0.11.0.1` parses interpolation as a variable followed by either `:` plus a partial call, a bracketed separator iteration, or plain interpolation. That means `${ items[, ]:row() }` is malformed; the supported applied-partial separator position is `${ items:row()[, ] }`.
- Upstream parser reference: `Text.DocTemplates.Parser` `pInterpolate`, `pPartial`, and `pSep` at `https://raw.githubusercontent.com/jgm/doctemplates/0.11.0.1/src/Text/DocTemplates/Parser.hs`.
- No Pandoc executable, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Changes

- `DocTemplate::parseAppliedPartialDirective()` now rejects variable-side separators before applied partial colons.
- The diagnostic is source-location aware through the existing relative-location wrapper:
  `Doctemplate applied partial separators must follow the partial call in warnings[, ]:warning-row() at <template>:1:4`.
- Added focused coverage proving the malformed syntax raises instead of silently rendering with an ignored separator.
- Updated the WordPress doctemplate review-packet self-test with the same malformed reviewer-row template guard.

## Verification

- Baseline before the new assertion:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1168 assertions, 0 failures`.
- Red-first after adding the test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  failed with `1 test files, 1168 assertions, 1 failures`; no exception was raised for `${ warnings[, ]:warning-row() }`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php`
  passed with `1 test files, 1169 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test`
  passed with `OK wordpress doctemplate review packet`.

## Mapping Delta

- `phpPass`: `2530 -> 2531`
- `benchmarkDenominator.mapped`: `2898 -> 2899`
- Added `mappedDoctemplateAppliedPartialSeparatorDiagnosticCases: 1`
- Added `doctemplateAppliedPartialSeparatorDiagnosticAssertions: 1`

## Dependency Closure

No new native support component is needed. This slice reuses the existing native PHP `DocTemplate` parser, source-location diagnostic wrapper, focused doctemplate tests, lane-local default/resource machinery, and WordPress doctemplate review-packet smoke.

## Non-Overlap

This does not repeat accepted doctemplate comments, delimiter trimming, comments whitespace, variable truthiness, loop separators, breakable spaces, parameterized pipes, partial rebinding, recursion-limit ordering, explicit nesting, default-template fallback, extension-qualified partial fallback, default partial fallback, unclosed separator diagnostics, malformed extra closing-bracket diagnostics, or nested wrapping slices. It only rejects variable-side separators before applied partial colons while preserving supported partial-side separators.
