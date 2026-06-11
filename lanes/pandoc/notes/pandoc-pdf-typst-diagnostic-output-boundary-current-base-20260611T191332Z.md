# pandoc-pdf-typst-diagnostic-output-boundary-current-base-20260611T191332Z

Slice: `plib-a5fj2`, PDF/Typst boundary provenance.

Base: current `origin/main` `6f71ba75aa5546ce46775c81c5ab610e92f4167f`.

`PdfEngineHandoff` now preserves Typst diagnostic output boundary provenance for
`--diagnostic-format` and `--color`. Plans retain invalid earlier values,
selected safe values, override history, review diagnostics, fake-run artifact
review metadata, and sequence summaries without invoking Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services, live
provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1794 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65588 assertions, 0 failures`

Lane status: `phpPass` moves `3104 -> 3105`.
