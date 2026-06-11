# pandoc-pdf-typst-system-font-boundary-current-base-20260611T1609Z

Slice: `plib-85idm`, PDF/Typst boundary provenance.
Required base: `b45804747`.

## Change

`PdfEngineHandoff` now preserves explicit Typst `--ignore-system-fonts`
boundary provenance. Plans record a `systemFonts` review entry with
`ignoreSystemFonts`, `systemFontAccess`, flag count, and declared font-path
count, and emit `typst-system-font-access` plus `typst-ignore-system-fonts`
diagnostics.

The same provenance is carried into fake-run artifact review metadata and
fake-run sequence summaries without invoking Pandoc, Typst, TeX/PDF engines,
browser renderers, external validators, online services, live provider tests, or
live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test files, 1675 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 63817 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3065 -> 3066`.
- Added one focused `PdfEngineHandoffTest` PASS case for Typst system font
  boundary provenance.
