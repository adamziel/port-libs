# Pandoc PDF/Typst handoff input provenance - 2026-06-30

## Slice

`PdfEngineHandoff::plan()` now emits a metadata-only `handoffInputProvenance` summary for PDF engine handoffs. The summary records the engine/intermediate source boundary, source hash and byte length when available, template/header source artifacts, resource path and manifest counts, remote/skipped resource references, template variable names, expected engine sidecars, and the relevant Typst boundary review statuses.

`PdfEngineHandoff::fakeRun()` carries that same object into the run result and nested `artifactProvenanceReview`, adding source/resource artifact hashes plus missing source/resource validation lists without executing Typst, TeX, browser engines, Pandoc, or external validators.

## Coverage

- Typst stdin source handoff now asserts that source-input review provenance is visible in the input summary and artifact review.
- Template/header/resource handoffs now assert source artifact counts, resource manifests, remote resource counters, and source/resource hashes in the nested provenance review.
- Missing source/resource files now surface through `handoffInputProvenance.validationStatus` and `validationIssues`.

## Validation

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with 3491 assertions and 0 failures.
