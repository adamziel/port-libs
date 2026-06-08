## markerpdf font-width indirect scalar slot boundary current-base 2026-06-08

Source truth:
- Upstream markerPDF searchable-PDF text extraction depends on pdftext/PDF parser font advances for word grouping before Markdown/WordPress paragraph import.
- This no-GPU PHP slice maps a bounded native parser boundary: simple-font `/Widths` array entries may be indirect scalar number objects, but a resolved helper object with trailing top-level operands is not a single width number and must not partially drive text-advance grouping.

Implementation:
- `PdfTextExtractor::simpleFontWidthArrayHasMalformedDeclaredToken()` now receives the resolved object table.
- Missing unresolved scalar width references remain tolerated as sparse slots, preserving the accepted unresolved-slot behavior.
- Present indirect width scalar helper objects that resolve to non-single-number bodies, such as `1000 /Tail`, fail closed by rejecting the explicit simple-font width array so Base14 fallback advances preserve the positioned word gap.

Verification:
- Red-first focused test before parser patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceIndirectScalarSlotBoundaryCurrentBaseTest.php` failed with `1 test files, 9 assertions, 1 failures`; the tailed helper imported `IllWord` instead of `Ill Word`.
- After patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceIndirectScalarSlotBoundaryCurrentBaseTest.php` passes with `1 test files, 20 assertions, 0 failures`.
- Adjacent checks: `php tools/run-tests.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceScalarOperandBoundaryCurrentBaseTest.php` passes with `2 test files, 67 assertions, 0 failures`.
- Existing broad font-width check: `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` passes with `1 test files, 642 assertions, 0 failures`.
- Font-width advance family check: `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvance*CurrentBaseTest.php` passes with `9 test files, 808 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-font-width-indirect-scalar-slot-currentbase.php` exits 0.

Dependency closure:
- No new support component is needed. The change reuses the existing native PDF parser/object-resolution and Base14 width fallback paths.

Scope limits:
- No Python, OCR, CUDA, model execution, raster rendering, external PDF tools, or live upstream benchmark runners are used.
