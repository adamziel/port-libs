# Pandoc PDF/Typst Default Open Output Viewer Provenance

Slice: `plib-0eala`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves bare Typst `--open` flags as
`default-viewer` entries in `typstBoundaryProvenance.openOutput.viewers` instead
of dropping them when no explicit viewer program is provided. The
`open-output` boundary matrix case now reports those default viewer launches via
`viewerCount` and `defaultViewerCount` while keeping `viewer` reserved for the
last explicit viewer.

Accounting:

- `phpPass`: `457 -> 458`
- `phpFail`: remains `0`
- Adds one focused `PdfEngineHandoffTest.php` behavior case.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3499 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Baseline-red outside this slice: `295 test files, 116986 assertions, 9781 failures`
  - Visible failures begin in `YamlMetadataReviewTest.php`.

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, office suite,
external validator, online service, live provider, Node tooling, or package
manager was invoked.
