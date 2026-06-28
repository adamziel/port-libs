# PDF/Typst Open Output Default Viewer Provenance

Slice: `plib-njoyx`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst `--open` entries that rely on the default viewer instead of dropping them from `openOutput.viewers`. Default-viewer records are marked as safe option values, included in `openOutput.viewer` and `openOutput.viewers`, and surfaced through the boundary matrix with default, specific, and invalid viewer counts. The slice remains fake-runner only and does not execute Typst or a PDF renderer.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3638 assertions, 0 failures`

Accounting:

- Focused `PdfEngineHandoffTest.php` keeps the mapped PDF/Typst boundary provenance coverage current after sequential merge resolution.
- `phpPass` remains `461`; `phpFail` remains `0`.
