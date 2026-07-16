# PDF/Typst sidecar output matrix history

Slice: `pandoc-pdf-typst-sidecar-output-matrix-history`

`PdfEngineHandoff` now carries dependency output, dependency format, and timings sidecar history into the Typst `sidecar-outputs` boundary matrix case. The focused fixture covers invalid overridden `--deps`, `--deps-format`, and `--timings` values through plan provenance, fake-run artifact review, and fake-run sequence summaries without executing Typst or any PDF engine.

Post-rebase verification for `plib-jwh8e` on current main `c0fff22ee3`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (`1 test files, 2942 assertions, 0 failures`)
- `php tools/run-tests.php lanes/pandoc/tests` (`258 test files, 175331 assertions, 0 failures`)

Accounting moves `mappedTypstBoundaryMatrixCases` 27 -> 28 and `typstBoundaryMatrixAssertions` 212 -> 223, with `phpPass` 16999 -> 17000 and `phpFail` staying at 0.

The slice stays native-PHP-only and does not invoke Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Typst, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests.
