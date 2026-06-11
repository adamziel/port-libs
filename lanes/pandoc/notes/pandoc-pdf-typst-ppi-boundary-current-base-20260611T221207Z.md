# Typst PPI boundary provenance (plib-m0tz9)

Hook: plib-m0tz9, Pandoc PDF/Typst boundary provenance core blocker slice 20260611T221207Z.
Scope: lanes/pandoc only.

## Implementation

- Added Typst `--ppi` option capture to `PdfEngineHandoff::typstBoundaryProvenanceFor()`.
- Preserved selected output-resolution PPI metadata, nonpositive/invalid/excessive diagnostics, repeated-option override records, and unsafe history rows.
- Propagated PPI provenance through plan diagnostics, fake-run artifact review, and fake-run sequence summaries without invoking Typst or any PDF engine.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed: 1 test file, 1830 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 66620 assertions, 0 failures.

Current main target: 4172e4cba.
