# PDF/Typst PPI Export Boundary Provenance

## Scope

Hooked bead: `plib-va3pk`.

This slice keeps the Pandoc PDF/Typst handoff native-PHP only and adds inert
review provenance for Typst `--ppi` export boundary options. It does not invoke
Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online
services, live provider tests, or live-service provider tests.

## Implementation

- `PdfEngineHandoff` now extracts `--ppi` values from Typst engine options.
- The selected PPI is recorded under `typstBoundaryProvenance.pdfExport.ppi`.
- Invalid, nonpositive, excessive, and overridden PPI boundaries are surfaced as
  review issues.
- Multi-value PPI histories are retained when historical values need review.
- Plan diagnostics now include `typst-ppi:<value>` or `typst-ppi:invalid`.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1830 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66450 assertions, 0 failures.
