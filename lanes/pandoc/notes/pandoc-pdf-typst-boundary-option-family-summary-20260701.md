# Pandoc PDF Typst Boundary Option-Family Summary - 2026-07-01

## Slice

`plib-ns6oq` adds a compact Typst boundary option-family summary to `PdfEngineHandoff`.
The summary groups existing metadata-only Typst boundary provenance into reviewer-facing families for root, environment, font paths, certificates, package storage, input variables, sidecar outputs, diagnostics, font access, output format, PDF export controls, feature gates, execution policy, creation timestamps, open-output side effects, and overrides.

## Evidence

- Plans now expose `optionFamilyCount`, `optionFamilyReviewCount`, `optionFamilyIssueCount`, sorted `optionFamilyCounts`, sorted `optionFamilyIssueCounts`, and ordered `optionFamilies` inside `typstBoundarySummary`.
- Plan diagnostics include `typst-boundary-summary-option-families:*` and `typst-boundary-summary-review-families:*`.
- Fake-run artifact provenance review and fake-run sequence outputs carry the summary unchanged without executing Typst, TeX/PDF engines, or external validators.

## Validation

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryOptionFamilySummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryOptionFamilySummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryOptionFamilySummaryTest.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundaryMatrixSummaryTest.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
