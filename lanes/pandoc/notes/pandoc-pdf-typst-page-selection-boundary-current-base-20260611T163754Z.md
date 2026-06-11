# pandoc-pdf-typst-page-selection-boundary-current-base-20260611T163754Z

Slice: `plib-gqges`, PDF/Typst boundary provenance.
Base: current `origin/main` `446b499ac`.

## Change

`PdfEngineHandoff` now preserves Typst `--pages` page-selection boundary
provenance without executing Typst. Plans record the selected page expression,
normalized selector segments, safety issues, duplicate selector overrides, and a
`typst-page-selection:*` diagnostic.

The provenance is carried through fake-run artifact review metadata and
fake-run sequence summaries. Repeated `--pages` values are reviewed as
`page-selection-boundary-overridden` while the final selected value remains
visible for audit.

No Pandoc, Typst, TeX/PDF engines, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1686 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 63940 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3071 -> 3072`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3193 -> 3194`.
- Added one focused `PdfEngineHandoffTest` PASS case for Typst page-selection
  boundary provenance.
