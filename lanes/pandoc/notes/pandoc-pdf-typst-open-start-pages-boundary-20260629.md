# pandoc-pdf-typst-open-start-pages-boundary-20260629

Slice: `plib-av5v9`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves Typst open-start page selection ranges such
as `--pages=-2` as bounded native provenance. The page-selection parser records
these segments as `range-to`, keeps the explicit end page, includes them in
overlap policy checks, and exposes `pageSelectionRangeToSegmentCount` in the
Typst boundary matrix.

No Pandoc, Typst, TeX/PDF engine, browser renderer, archive tool, Node tooling,
or external validator is invoked. The slice only extends native PHP review
metadata for Typst PDF export boundaries.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
