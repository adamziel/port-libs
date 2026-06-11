# pandoc-pdf-typst-page-selection-boundary-current-base-20260611T162630Z

Slice: `plib-j8ols`, PDF/Typst boundary provenance.

## Change

`PdfEngineHandoff` now preserves Typst `--pages` compile boundary provenance.
Plans record a `typstPageSelectionPolicy` with raw selector values, selected
value, parsed page/range/half-open range segments, malformed selector issues,
and repeated-option override history. The same policy is carried through
fake-run results, artifact provenance review metadata, and fake-run sequence
summaries.

The policy is review-only: malformed or overridden selectors move artifact
provenance to `review` without invoking Typst or changing existing hard-failure
conditions such as missing output artifacts or root-boundary violations.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1691 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed after rebase on `4c7bc3880`: 44 test files, 63900 assertions,
  0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3069 -> 3070`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3193 -> 3194`.
- Added `mappedTypstPageSelectionBoundaryProvenanceCases = 1`.
- Added `typstPageSelectionBoundaryProvenanceAssertions = 16`.

No Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines, browser renderers,
external validators, office suites, zip/unzip, online services, live provider
tests, or live-service provider tests were run.
