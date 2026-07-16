# Pandoc PDF/Typst Input Variable Policy Slice

Slice: `pandoc-pdf-typst-input-variable-policy-20260627`

`PdfEngineHandoff` now emits bounded Typst input-variable policy provenance for
duplicate or invalid `--input` assignments. The policy groups raw values by
input name, records the selected value that Typst would see, counts duplicate
assignment groups, and carries the selected-value map into the Typst boundary
matrix without executing Typst or a PDF engine.

The focused test fixture now constructs PDF handoff metadata directly instead of
depending on Markdown YAML front matter parsing, keeping the PDF handoff tests
focused on handoff behavior.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  (`1` file, `3436` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests`
  did not pass on the rebased branch (`292` files, `116389` assertions,
  `9782` failures); visible failures begin outside this slice in
  `YamlMetadataReviewTest.php`.

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, external
validator, network service, or office suite was invoked.
