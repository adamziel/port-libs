# Pandoc PDF/Typst Attached Short Format Boundary

Slice: `plib-jp179` / `20260612T025730Z`.

## Summary

`PdfEngineHandoff` now treats attached short Typst output format options such as `-fsvg` and `-fpdf` as explicit output-format declarations. The handoff records both values in `typstOutputFormatPolicy`, preserves stale non-PDF history, marks the final selected format, emits override diagnostics, and carries the same policy through fake-run artifact review plus fake-run sequence summaries without invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`: 1 test file, 2029 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 69796 assertions, 0 failures
