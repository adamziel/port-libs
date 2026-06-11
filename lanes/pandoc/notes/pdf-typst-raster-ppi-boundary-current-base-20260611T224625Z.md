# Typst Raster PPI Boundary Provenance

- Bead: `plib-r1mzj`
- Base: `9c821d42a1800c1547f75c1e9ca0cde2757390df` (`9c821d42a`)
- Scope: PDF/Typst boundary provenance under `lanes/pandoc`

## Slice

`PdfEngineHandoff` now records Typst `--ppi` raster pixel-density options in
`typstBoundaryProvenance` without invoking Typst. The review packet preserves:

- the selected pixels-per-inch value
- invalid prior values
- override history
- rasterization review issues
- plan diagnostics
- fake-run artifact review propagation
- sequence summary propagation

The validator accepts bounded positive integer PPI values and marks empty,
non-numeric, non-positive, or excessive values for review.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1847 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66756 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engine, browser renderer, external validator, online
service, live provider test, or live-service provider test was invoked.
