# Pandoc PDF/Typst Zero Dependency Sidecar Slice

Date: 2026-06-14

## Scope

This slice covers explicit Typst `--deps-format=zero` dependency sidecars in the bounded native PHP PDF engine handoff. It does not execute Pandoc, Typst, TeX/PDF engines, browser renderers, online services, live provider tests, or external validators.

## Behavior

- `PdfEngineHandoff` reads the selected Typst dependency format from boundary provenance before decoding dependency artifacts.
- Explicit `zero` sidecars are parsed as NUL-delimited dependency input lists.
- Local file inputs, external inputs, and `typst-package:` dependencies feed the existing dependency, package, and root-boundary provenance paths.
- Zero-format sidecars do not declare output targets, so `typstDependencyOutputPolicy` stays `review` with missing-output-target metadata instead of synthesizing an output edge.
- Existing make-style dependency parsing remains the output-target parity path.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` -> 1 file, 2,543 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 83,483 assertions, 0 failures

## Accounting

- `phpPass`: 3,527 -> 3,528
- mapped upstream cases: 3,444 -> 3,445
- `mappedTypstZeroDependencySidecarCases`: 1
- `typstZeroDependencySidecarAssertions`: 34
- PDF/Typst boundary/provenance local evidence: 50 -> 51 cases
