# pandoc-pdf-typst-nested-json-warning-provenance-20260617

Slice: `pandoc-pdf-typst-nested-json-warning-provenance`

This PDF/Typst recovery slice extends Typst JSON warning provenance without
executing Typst or a PDF engine. `PdfEngineHandoff` now recovers warning source
paths from nested `span.input`, nested `span.source`, and sibling `input`
objects, and accepts `lineNumber`, `columnNumber`, and `character` coordinate
aliases before normalizing paths through the existing engine dependency boundary
classifier.

The focused fixture is
`fake runner recovers nested typst json warning source provenance without executing`
in `PdfEngineHandoffTest.php`. It covers inside-root, outside-root, and sibling
range-source recovery while preserving warning hints, source issue diagnostics,
artifact review payloads, and fake-run sequence summaries.

Verification:

- Rebased on `9b3acbd7db`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  (`1` file, `2749` assertions, `0` failures).
- `php tools/run-tests.php lanes/pandoc/tests`
  (`258` files, `175138` assertions, `0` failures).
