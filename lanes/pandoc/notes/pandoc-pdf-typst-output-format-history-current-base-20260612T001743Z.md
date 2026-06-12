# PDF/Typst output format history current-base slice

Date: 2026-06-12 UTC
Bead: plib-02gxp

This slice extends the bounded PDF/Typst handoff for Typst `--format` output requests.

`PdfEngineHandoff` now preserves repeated and missing `--format` option provenance as `typstOutputFormatPolicy` review metadata:

- per-option `formatHistory` entries for missing, stale non-PDF, and selected final values;
- `format-boundary-overridden` records when multiple format options are present;
- plan diagnostics for format history and overrides;
- fake-run artifact review and `finalTypstOutputFormatPolicy` sequence propagation.

The slice remains metadata-only. It does not execute or require Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed: 1 test file, 1877 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 67616 assertions, 0 failures.
