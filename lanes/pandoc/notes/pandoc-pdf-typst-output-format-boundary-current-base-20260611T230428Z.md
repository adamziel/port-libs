# Pandoc PDF/Typst Output Format Boundary Provenance

Slice: `plib-ymk36`, PDF/Typst boundary provenance core blocker slice 20260611T230428Z.

Base: current `origin/main` `67332814bf`.

`PdfEngineHandoff` now preserves Typst `--format` output-format boundary
provenance inside `typstBoundaryProvenance` without executing Typst. Plans
retain the selected format, non-PDF review issue, override history, deterministic
diagnostics, and propagation through fake-run artifact review plus fake-run
sequence summaries while keeping the existing `typstOutputFormatPolicy`
compatibility packet intact.

This does not repeat root, font, certificate, package/cache, input, timestamp,
pages, PDF standard, feature gate, jobs, timings, diagnostic-output, dependency
sidecar, system-font, embedded-font, open-output, or rasterization PPI boundary
slices. It owns only the `--format` output-format option boundary.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1853 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66922 assertions, 0 failures.

## Accounting

Added one focused PHP PASS case with 18 focused assertions:
`mappedTypstOutputFormatBoundaryCases = 1`,
`typstOutputFormatBoundaryAssertions = 18`.

Lane `phpPass`: 3137 -> 3138; `phpFail` remains 0.
