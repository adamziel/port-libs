# PDF/Typst input value reference boundary slice

Slice: `plib-wmjxd`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
path-like and URI-like `--input` values. Normal scalar inputs keep the existing
`inputVariables` shape, while reference-looking values now produce an additive
`inputValueReferencePolicy` with relative, URI, absolute, workspace, and invalid
reference counts plus issue rollups.

The policy keeps safe relative values as provenance and marks remote or invalid
parent-directory references for review. It is preserved through plan diagnostics,
fake-run artifact provenance review, and fake-run sequence summaries.

No Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests were
invoked.

Verification on current main `1cc64b1e16`:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  (1 test file, 2003 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests`
  (44 test files, 69655 assertions, 0 failures)
