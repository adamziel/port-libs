# Pandoc PDF/Typst PPI Rasterization Boundary Provenance

Slice: `plib-g8xgq`, PDF/Typst boundary provenance core blocker slice 20260611T225346Z.

Base: current `origin/main` `e25fac1262`.

`PdfEngineHandoff` now preserves Typst `--ppi` rasterization boundary provenance
without executing Typst. Plans retain selected PPI values, safe numeric PPI
metadata, invalid earlier values, override history, deterministic diagnostics,
and propagation through fake-run artifact review plus fake-run sequence summaries.

This does not repeat root, font, certificate, package/cache, input, timestamp,
pages, PDF standard, feature gate, jobs, timings, diagnostic-output, dependency
sidecar, system-font, embedded-font, or open-output boundary slices. It owns only
the rasterization PPI option boundary.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1847 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 66855 assertions, 0 failures.

## Accounting

Added one focused PHP PASS case with 12 focused assertions:
`mappedTypstPpiRasterizationBoundaryCases = 1`,
`typstPpiRasterizationBoundaryAssertions = 12`.

Lane `phpPass`: 3135 -> 3136; `phpFail` remains 0.
