# Pandoc PDF/Typst rasterization PPI boundary provenance

This slice stays inside native PHP PDF/Typst handoff planning and fake-run
review metadata. `PdfEngineHandoff` now preserves Typst `--ppi` rasterization
boundary provenance for reviewer packets:

- raw `--ppi` option history;
- selected pixels-per-inch value;
- invalid and out-of-range PPI review issues;
- repeated `--ppi` override history;
- plan diagnostics, fake-run artifact review, and sequence summaries.

No Pandoc, Typst, TeX/PDF engine, browser renderer, external PDF validator,
online service, live provider test, or live-service provider test was invoked.

Verification on current main `ac1f74a84`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test file, 1686 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 64058 assertions, 0 failures`

Accounting:

- `lane-status.json` `phpPass`: `3074` -> `3075`
- `phpFail`: `0`
