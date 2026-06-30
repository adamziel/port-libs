# Pandoc PDF Page Viewport Policy Slice

- Slice: `plib-9lccc`
- Area: PDF/Typst boundary provenance
- Behavior: produced-PDF page viewport measurement review policy

`PdfEngineHandoff` now summarizes page `/VP` viewport metadata into
`pdfPageViewportPolicy` and `finalPdfPageViewportPolicy`. The policy carries
viewport counts, affected page numbers, named and boxed viewport counts,
measurement subtype counts, scale-ratio counts, unit-category totals, unit
labels, and bounded review diagnostics for missing boxes, missing measure
subtypes, missing scale ratios, and nonpositive unit conversions.

This stays inside native PHP produced-byte inspection. It does not execute
Typst, TeX/PDF engines, external PDF validators, browser renderers, office
suites, or network fetches.

Validation:

```bash
php -l lanes/pandoc/src/PdfEngineHandoff.php
php -l lanes/pandoc/tests/PdfEngineHandoffTest.php
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 3690 assertions, 0 failures`.
