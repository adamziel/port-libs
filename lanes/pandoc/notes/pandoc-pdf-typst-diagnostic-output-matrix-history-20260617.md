# PDF/Typst diagnostic output matrix history

Slice: `pandoc-pdf-typst-diagnostic-output-matrix-history`

`PdfEngineHandoff` now carries Typst diagnostic format/color history issues and diagnostic output override issues into the `diagnostic-output` boundary matrix case. The focused fixture covers invalid overridden `--diagnostic-format` and `--color` values through plan provenance, fake-run artifact review, and fake-run sequence summaries without executing Typst or any PDF engine.

Post-rebase verification for `plib-hh29k` on current main `2e12ace7af`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file / 2839 assertions / 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (258 files / 175228 assertions / 0 failures)

Accounting moves `mappedTypstBoundaryMatrixCases` 22 -> 23 and `typstBoundaryMatrixAssertions` 124 -> 134, with `phpPass` 16993 -> 16994 and `phpFail` staying at 0.

The slice stays native-PHP-only and does not invoke Pandoc, cmark/commonmark runners, Cabal/Haskell runners, Typst, TeX/PDF engines, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests.
