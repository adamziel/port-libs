# pandoc-pdf-typst-open-output-boundary-current-base-20260611T1911Z

Slice: `plib-3fsc1`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst `--open` output-viewer side-effect
provenance as inert review metadata. Plans record the flag count in
`typstBoundaryProvenance.openOutput`, mark the boundary for review with
`open-output-side-effect-boundary`, and carry the same metadata through
`fakeRun()`, `artifactProvenanceReview`, and `fakeRunSequence()` summaries
without invoking Typst or opening any output.

Accounting:

- `phpPass`: `3103 -> 3104`.
- Added one focused `PdfEngineHandoffTest` PASS case.
- Added `mappedTypstOpenOutputBoundaryCases = 1`.
- Added `typstOpenOutputBoundaryAssertions = 11`.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test file, 1782 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 65466 assertions, 0 failures`.

No Pandoc, Cabal/Haskell runners, Typst, TeX/PDF engines, browser renderers,
office suites, zip/unzip, Jupyter, Node tooling, external validators, online
services, live provider tests, or live-service provider tests were run.
