# PDF/Typst short output-format flag boundary

Slice: plib-det6p, 2026-06-12T000141Z.

## Scope

- Bounded native PHP PDF/Typst boundary provenance only.
- `PdfEngineHandoff` now treats Typst `-f` as the short value-taking form of `--format` for output-format policy review.
- The new coverage keeps explicit non-PDF short-format values visible in plan diagnostics, fake-run artifact provenance review, and fake-run sequence summaries.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1886 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67870 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
