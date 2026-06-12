# pandoc-pdf-typst-safe-override-history-20260612T024631Z

Slice: `plib-6h4ie`, PDF/Typst boundary provenance.

Base: current `origin/main` `713ba0d252`.

`PdfEngineHandoff` now preserves parsed Typst boundary histories whenever an
option is repeated, even when every candidate value is otherwise safe. Safe
root, package path/cache, creation timestamp, timings, diagnostic output, and
dependency format overrides now retain the structured entries that explain the
selected boundary instead of only listing raw override strings.

The regression remains native PHP-only and does not invoke Pandoc, Typst,
TeX/PDF engines, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2005 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69625 assertions, 0 failures`

Parity accounting: one new focused PHP PASS case; lane `phpPass` moves
`3176 -> 3177`.
