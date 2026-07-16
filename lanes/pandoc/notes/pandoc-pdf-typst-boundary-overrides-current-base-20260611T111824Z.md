# pandoc-pdf-typst-boundary-overrides-current-base-20260611T111824Z

Slice: `plib-zctcb`, PDF/Typst boundary provenance.
Required base: `a2c06142a7e251525c2e35f19982c43208d7be1e`.

## Change

`PdfEngineHandoff` now preserves override provenance for repeated singleton
Typst compile boundary options:

- `--root`
- `--package-path`
- `--package-cache`
- `--creation-timestamp`

The selected final value remains in the existing boundary fields, while the
discarded and selected values are recorded in `overrides` with option name,
count, raw values, selected value, and review issue. Plans also emit
`typst-boundary-overrides:N` diagnostics, and fake-run artifact review plus
sequence summaries carry the same provenance without invoking Pandoc, Typst,
TeX/PDF engines, browser renderers, or external validators.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1629 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 62677 assertions, 0 failures.
