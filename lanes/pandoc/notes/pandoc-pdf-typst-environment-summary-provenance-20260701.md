# PDF/Typst environment summary provenance

Slice: `plib-tiwfk`

`PdfEngineHandoff` now carries aggregate Typst environment provenance in
`typstBoundarySummary`:

- total observed Typst environment variable count;
- shadowed environment variable count;
- deterministic shadowed environment variable names.

The summary complements the existing `environment-shadows` boundary matrix case
so package review can see environment influence and CLI shadowing without
reading engine outputs or executing Typst/PDF engines.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed
  with 1 file, 3,626 assertions, and 0 failures.
