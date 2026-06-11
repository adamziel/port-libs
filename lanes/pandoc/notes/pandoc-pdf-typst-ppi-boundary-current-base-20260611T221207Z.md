# pandoc-pdf-typst-ppi-boundary-current-base-20260611T221207Z

Slice: `plib-m0tz9`, PDF/Typst boundary provenance.

Base: current `origin/main` `7f33388364a4c70802915a0cdb159c7a8c3d76e3`.

`PdfEngineHandoff` now preserves Typst output-resolution provenance for
`--ppi`. Plans retain nonpositive and excessive PPI values, selected PPI
metadata, override history, review diagnostics, fake-run artifact review
metadata, and sequence summaries without invoking Pandoc, Typst, TeX/PDF
engines, browser renderers, external validators, online services, live provider
tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1830 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66530 assertions, 0 failures`

Lane status: `phpPass` moves `3130 -> 3131`.

Mapping delta:

- `mappedTypstPpiBoundaryCases: 1`
- `typstPpiBoundaryAssertions: 12`
