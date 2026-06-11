# Pandoc PDF/Typst dependency-file boundary provenance

Slice: `plib-oel9k` / 2026-06-11T173315Z.

## Scope

This slice keeps the PDF/Typst work bounded to `PdfEngineHandoff` and maps Typst dependency sidecar path provenance for `--deps` / `--make-deps`.

## Behavior

- `PdfEngineHandoff::engineDependencyFileFor()` now resolves the final declared Typst dependency sidecar option through the shared engine-option parser, aligning expected artifact planning with existing last-value boundary provenance semantics.
- Typst boundary provenance now includes `dependencyFile` for the selected dependency sidecar path.
- Unsafe or missing dependency sidecar paths are reviewed with `dependency-file-*` issues.
- Repeated dependency sidecar options now surface `dependency-file-boundary-overridden` plus `dependencyFileHistory` when prior unsafe values need review.
- Plan diagnostics now include `typst-dependency-file-boundary:<path>`.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1690 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 64403 assertions, 0 failures`

## Counters

- `lane-status.json` `phpPass`: `3082 -> 3083`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3201 -> 3202`
- Added `mappedTypstDependencyFileBoundaryProvenanceCases = 1`
- Added `typstDependencyFileBoundaryProvenanceAssertions = 15`
