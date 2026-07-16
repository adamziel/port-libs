# pandoc-pdf-typst-dependency-format-boundary-current-base-20260611T194501Z

Slice: `plib-wgnqh`, PDF/Typst boundary provenance.

Base: current `origin/main` `282d4fe1b44779cefd27dddf2be66cec4aa9b4ff`.

`PdfEngineHandoff` now preserves Typst dependency sidecar format provenance for
`--deps-format`. Plans retain invalid earlier values, selected safe format
metadata, override history, review diagnostics, fake-run artifact review
metadata, and sequence summaries without invoking Pandoc, Typst, TeX/PDF
engines, browser renderers, external validators, online services, live provider
tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1818 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66105 assertions, 0 failures`

Lane status: `phpPass` moves `3121 -> 3122`.
