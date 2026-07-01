# PDF AcroForm Calculation Order Policy (plib-ik65p)

## Scope

- Added `pdfAcroFormCalculationOrderPolicy` to produced-PDF inspection, fake-run output, and sequence output.
- Summarizes AcroForm `/CO` calculation-order entries without evaluating calculations: declared field count, ordered field objects, resolved and missing field references, undeclared references, field names, field type labels, flag counts, and review issues.
- Emits deterministic diagnostics for policy status, entry counts, resolved/missing/undeclared fields, type labels, flag names, and issue codes.
- Keeps raw `pdfAcroFormCalculationOrder` entries unchanged for consumers that need per-entry provenance.

## Validation

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with 3,700 assertions and 0 failures.

No Typst, TeX/PDF engines, office suites, browser engines, Pandoc shell-outs, or external validators were used.
