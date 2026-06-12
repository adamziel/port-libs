# PDF/Typst signed numeric boundary provenance

Slice: plib-9sj9p, 2026-06-12T004432Z.

## Scope

- Bounded native PHP PDF/Typst boundary provenance only.
- `PdfEngineHandoff` now preserves signed numeric values following Typst numeric boundary options instead of treating them as missing option values.
- Covered options: `--pages`, `--ppi`, `--jobs`/`-j`, and `--creation-timestamp`.
- The coverage keeps invalid signed values visible in plan diagnostics, fake-run artifact provenance review, and fake-run sequence summaries.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1901 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 68084 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
