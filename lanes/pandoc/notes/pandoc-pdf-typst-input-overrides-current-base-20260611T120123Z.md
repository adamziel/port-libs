# pandoc-pdf-typst-input-overrides-current-base-20260611T120123Z

Slice: `plib-lkp6g`, PDF/Typst boundary provenance.
Required base: `117b8ebfc0123b899ea30bb154a093d2f02a6a67`.

## Change

`PdfEngineHandoff` now records duplicate Typst `--input` variable names as
boundary override provenance. Every supplied input assignment remains visible in
`inputVariables`; repeated valid names add an `overrides` entry keyed as
`input:<name>` with value count, ordered values, selected final value, and a
review issue such as `input-variable-boundary-overridden:audience`.

The provenance is preserved in plans, fake-run artifact review metadata, and
fake-run sequence summaries without invoking Pandoc, Typst, TeX/PDF engines,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1665 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 63575 assertions, 0 failures.
