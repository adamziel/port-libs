# Pandoc PDF/Typst Export Matrix Controls

Slice: `plib-0eala`, PDF/Typst boundary provenance.
Base after rebase: `1a3296aaad`.

This slice extends native PHP `PdfEngineHandoff` Typst boundary matrix
provenance for PDF export controls that were already recorded in the core
`pdfExport` payload but not summarized in the `pdf-export-controls` matrix
case.

Behavior:

- Adds `tagsDisabled` and `tagsFlagCount` to the `pdf-export-controls` matrix
  case for explicit Typst `--no-pdf-tags`.
- Adds `prettyEnabled` and `prettyFlagCount` to the same matrix case for
  explicit Typst `--pretty`.
- Preserves existing `pdfExport`, summary, diagnostic, fake-run artifact review,
  and fake-run sequence behavior without invoking external engines.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2667 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258 test files, 175056 assertions, 0 failures`

Accounting:

- `phpPass`: `16982 -> 16983`
- `phpFail`: remains `0`
- Adds `mappedTypstPdfExportMatrixControlCases = 1`
- Adds `typstPdfExportMatrixControlAssertions = 16`
- Mapped upstream manifest cases: `16568 -> 16569`
- Root mapped inventory: `16537 -> 16538`
- Benchmark denominator mapped cases: `3706 -> 3707`

No Pandoc binary, Cabal/Haskell runner, Typst/PDF engine, TeX/PDF engine,
browser renderer, external validator, online service, live provider test, or
live-service provider test was invoked.
