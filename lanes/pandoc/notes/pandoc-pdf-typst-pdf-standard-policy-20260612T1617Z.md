# pandoc-pdf-typst-pdf-standard-policy-20260612T1617Z

Slice: `plib-9sj9p`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` Typst boundary provenance for
PDF standard combinations. Plans now emit `pdfStandardPolicy` review metadata
when a selected `--pdf-standard` value combines incompatible or ambiguous
targets, including multiple base PDF versions, multiple PDF/A targets, PDF/A
with PDF/UA, and PDF 2.0 with PDF/UA-1. The policy is preserved through plan
diagnostics, fake-run artifact review, and multipass sequence summaries without
invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

Verification:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file, 2141 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 72081 assertions, 0 failures)

Accounting:
- Adds one focused `PdfEngineHandoffTest.php` PASS case for Typst PDF standard
  policy provenance.
- Moves direct PHP pass accounting `phpPass` 3236 -> 3237 with `phpFail` still
  0 and mapped denominator 3256 -> 3257.
- Adds `mappedTypstPdfStandardPolicyCases = 1` and
  `typstPdfStandardPolicyAssertions = 23`.
