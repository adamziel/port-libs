# plib-jtjwe PDF page structure parent tree coverage

Date: 2026-06-17
Base: origin/main 42a9ad6e30
Scope: PDF/Typst boundary provenance recovery, native PHP only.

- `PdfEngineHandoff` carries page `/StructParents` indexes into the PDF structure parent tree policy.
- The policy reports `pageStructParentIndexes` and `missingPageStructParentIndexes` from produced PDF bytes.
- Missing `/ParentTree` coverage for page structure-parent indexes emits `page-struct-parent-missing-parent-tree` and deterministic diagnostics.
- The fixture is bounded to fake-runner produced bytes and does not invoke Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

Accounting:

- `mappedPdfPageStructureParentTreeCoverageCases`: `0 -> 1`
- `pdfPageStructureParentTreeCoverageAssertions`: `0 -> 11`
- `phpPass`: `17002 -> 17003`
- Upstream manifest mapped cases: `16588 -> 16589`
- Root mapped inventory: `16557 -> 16558`
- Benchmark denominator mapped cases: `3726 -> 3727`

Post-rebase verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`
- JSON status/manifest validation, conflict-marker scan, and `git diff --check`

Observed post-rebase coverage:

- Focused `PdfEngineHandoffTest.php`: 1 file, 2992 assertions, 0 failures.
- Full `lanes/pandoc/tests`: 258 files, 175381 assertions, 0 failures.
