# Pandoc PDF/Typst PDF Standard Safe History Slice

Slice: `plib-hq24f`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves safe repeated Typst `--pdf-standard` values in
`pdfStandardHistory`, not only histories with invalid entries. This keeps
review provenance for standards that are individually valid but overridden at
the handoff boundary, and carries the history into the Typst boundary matrix,
fake-run artifact review, and fake-run sequence summary without executing Typst
or any PDF engine.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  (`1` file, `3457` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests`
  did not pass on the rebased branch (`294` files, `116701` assertions,
  `9781` failures); visible failures begin outside this slice in
  `YamlMetadataReviewTest.php`.

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, external
validator, network service, or office suite was invoked.
