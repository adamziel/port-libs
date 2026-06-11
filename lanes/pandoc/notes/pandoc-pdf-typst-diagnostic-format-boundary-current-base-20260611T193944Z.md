# PDF/Typst Diagnostic Format Boundary Provenance

## Slice

Current base: `1d859c516` (`pandoc: normalize mathscinet csl aliases (plib-pkdn9)`).

This slice preserves Typst `--diagnostic-format` as inert PDF/Typst boundary
provenance. It records selected diagnostic format, parser profile, warning
provenance expectation, unsupported-format diagnostics, and repeated option
override history in plan, fake-run artifact review, and sequence summaries.

## Scope

Changed only:

- `lanes/pandoc/src/PdfEngineHandoff.php`
- `lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `lanes/pandoc/lane-status.json`

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1798 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 65567 assertions, 0 failures

## Delta

- Added `mappedTypstDiagnosticFormatBoundaryCases: 1`.
- Added `typstDiagnosticFormatBoundaryAssertions: 16`.
- Moved `phpPass` from 3105 to 3106.
