# pandoc-pdf-typst-diagnostic-format-source-style-current-base-20260611T191332Z

Slice: `plib-a5fj2` on current main `0ba6b0e01`.

## Scope

This bounded PDF/Typst handoff slice builds on the accepted diagnostic output
boundary provenance for Typst `--diagnostic-format` and `--color`. It adds only
diagnostic-format source-location style provenance, without repeating root,
package/cache, input, warning-source, timing, feature, PDF export, PDF standard,
system-font, open-output, or diagnostic color work.

## Change

`PdfEngineHandoff` now labels diagnostic format entries with
`sourceLocationStyle`:

- `human` => `expanded`;
- `short` => `compact`;
- `json` => `structured`;
- invalid/missing formats => `unknown`.

The label is preserved in plan metadata, fake-run artifact review, and sequence
summaries. No Pandoc, Typst, TeX/PDF engine, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Passed: `1 test file, 1802 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Passed: `44 test files, 65646 assertions, 0 failures`.
