# Pandoc PDF/Typst runtime source summary provenance

Bead: plib-ibcv7
Date: 2026-07-02

## Slice

`PdfEngineHandoff` now carries a metadata-only `typstRuntimeSourceSummary` for fake Typst runs. The summary rolls up existing timing, warning, and error source policies into one reviewer surface with:

- channel counts and per-channel summaries;
- source kind, source class, and boundary-status totals;
- distinct source files;
- Typst package references and package references by channel;
- source issue counts and issue codes.

The summary is exposed in the fake-run result, `artifactProvenanceReview`, diagnostics, and `fakeRunSequence()` final state.

## Constraints

The slice reuses existing bounded timing JSON and diagnostic JSON parsing. It does not execute Typst, PDF engines, Pandoc, browser/TeX engines, package managers, validators, or live services.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTypstRuntimeSourceSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstRuntimeSourceSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstTimingSourcePolicyTest.php lanes/pandoc/tests/PdfEngineHandoffTypstWarningSourcePolicyTest.php lanes/pandoc/tests/PdfEngineHandoffTypstErrorSourcePolicyTest.php`
