# Duplicate Simple-Font Width Advance Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T231109Z`
Accepted base: `f685254b0778d68ce6aa741679af3d6e4e13f252`

## Behavior

Native simple-font width metrics now use the current duplicate top-level
`/FirstChar`, `/LastChar`, and `/Widths` dictionary values before text-advance
gap grouping. This prevents stale first width arrays from creating false
WordPress paragraph gaps or tiny styled-span bboxes when a later dictionary
entry supplies the active width range.

## Evidence

- Red-first no-file probe before the source fix produced stale first-width
  output lines `["AB CD"]` and stale styled bboxes
  `[[0,0,6,12],[24,0,30,12]]`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontDuplicateWidthAdvanceBoundaryCurrentBaseTest.php`
  passed: `1 test files, 16 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontDuplicateWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvancePositionOperandBoundaryCurrentBaseTest.php`
  passed: `4 test files, 675 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-font-duplicate-width-advance-currentbase.php`
  emitted `lines=["ABCD","EFGH"]`,
  `duplicate_width_current_range_selected=true`,
  `stale_width_false_gap_excluded=true`,
  `stale_width_tiny_bboxes_excluded=true`,
  `styled_span_bboxes_preserved=true`,
  `font_resource_names_visible=false`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.

## Scope

This does not repeat exact-generation `/Widths`, malformed simple-font width
rows, quote/Td/TJ positioning, CIDFont `/W` or `/W2`, Type3 FontMatrix, or
overlarge operand boundary coverage. The slice is limited to duplicate
top-level simple-font width-range dictionary keys affecting native text
advance and WordPress paragraph grouping.

Dependency closure: no new support component is needed. The patch reuses the
native PHP PDF object resolver and text extractor; no GPU/model/OCR,
Streamlit/FastAPI model worker, raster renderer, PDF action execution, or
external PDF tool was run.

Root harness: not run - isolated micro-slice.
