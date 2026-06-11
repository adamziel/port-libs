# Typst Output Format Boundary Slice

Bead: `plib-ghr7a`

Current base: `e9d25106ae`

Change:
- `PdfEngineHandoff` now includes Typst `--format` values in `typstBoundaryProvenance`.
- The boundary packet records selected output format metadata, non-PDF issue codes, override summaries, and output-format history.
- Added a focused fake-run sequence test proving the provenance survives plan, artifact review, and sequence handoff without executing Typst or PDF engines.

Verification:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file, 1860 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 67301 assertions, 0 failures)

No Pandoc, Typst, TeX/PDF engine, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
