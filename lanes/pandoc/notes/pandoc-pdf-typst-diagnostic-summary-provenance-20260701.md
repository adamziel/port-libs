# PDF/Typst diagnostic summary provenance

Slice: `plib-lh4od`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now carries compact Typst diagnostic-output counters in
`typstBoundarySummary`:

- selected diagnostic format/color control count;
- diagnostic format and color history counts;
- diagnostic output override count;
- invalid diagnostic-output count.

The summary complements the existing `diagnostic-output` boundary matrix case
so package review can see diagnostic output boundary pressure without reading
engine outputs or executing Typst/PDF engines.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed
  with 1 file, 3,709 assertions, and 0 failures.
