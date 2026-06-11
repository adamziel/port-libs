# Typst PPI Boundary Provenance - 2026-06-11T224847Z

Bead: plib-uf5og

Scope: lanes/pandoc PDF/Typst output-resolution boundary provenance.

Implemented native PHP `--ppi` parsing in `PdfEngineHandoff` plan provenance:

- Captures selected PPI value, `pixelsPerInch` int/null, safe flag, and empty/invalid/nonpositive/excessive issue codes.
- Records repeated `--ppi` override history with `ppi-boundary-overridden`.
- Emits `typst-output-ppi` and `typst-output-resolution-issues` diagnostics.
- Preserves provenance through `fakeRun` and `fakeRunSequence`.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`: 1 test file, 1847 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 66756 assertions, 0 failures
