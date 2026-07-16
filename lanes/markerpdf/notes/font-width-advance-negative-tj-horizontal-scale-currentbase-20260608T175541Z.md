# markerpdf font-width negative TJ horizontal scale current-base 2026-06-08

Source truth:
- Upstream markerPDF searchable-PDF extraction relies on pdftext/PDF parser text positioning before Markdown and WordPress paragraph import.
- This no-GPU PHP slice maps a bounded native parser boundary: `TJ` numeric adjustments are text-space cursor moves, so a negative horizontal text scale mirrors which adjustment direction creates a visible word gap.

Implementation:
- `PdfTextExtractor::decodePositionedTextOperand()` now classifies horizontal `TJ` spacing through a sign-aware helper.
- Positive horizontal scale keeps the accepted behavior: negative `TJ` adjustments create forward word gaps, and positive adjustments compact/backtrack.
- Negative horizontal scale mirrors that direction: negative `TJ` adjustments preserve mirrored visible gaps, while positive adjustments compact/backtrack instead of inserting a false space.

Verification:
- Red-first focused test before parser patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceNegativeTjHorizontalScaleBoundaryCurrentBaseTest.php`
  failed with `1 test files, 1 assertions, 1 failures`; expected `['AB CD','EF GH','IJKL']`, actual `['ABCD','EF GH','IJ KL']`.
- After patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceNegativeTjHorizontalScaleBoundaryCurrentBaseTest.php`
  passes with `1 test files, 19 assertions, 0 failures`.
- Font-width advance family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvance*CurrentBaseTest.php`
  passes with `17 test files, 944 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-font-width-negative-tj-horizontal-scale-currentbase.php`
  exits 0 and emits paragraphs `AB CD`, `EF GH`, and `IJKL`.

Dependency closure:
- No new support component is needed. The change reuses the native PDF object scanner, content parser, text-state parser, simple-font widths, `TJ` array parser, and WordPress smoke renderer.

Scope limits:
- No Python, OCR, CUDA, model execution, raster rendering, external PDF tools, or live upstream benchmark runners were used.
- Root harness: not run - isolated micro-slice.
