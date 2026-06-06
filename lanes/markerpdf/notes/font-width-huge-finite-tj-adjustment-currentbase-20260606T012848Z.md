# Font Width Huge Finite TJ Adjustment Boundary

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T012848Z`

Base accepted HEAD: `d6b4b18da7eea175fa2910b233c1d191e05e49c8`

## Source Truth

- Upstream markerPDF's searchable-PDF text extraction boundary is represented in the lane manifest as the pdftext/PDFium text boundary, while this no-GPU port implements native PHP fail-closed behavior for text/content-stream parsing.
- PDF `TJ` arrays interleave text strings with numeric text-position adjustments. Those numbers are advance operands in text space; finite but absurd operands must not create false WordPress word gaps or unbounded styled bboxes.
- This slice reuses the native `MAX_FONT_ADVANCE_METRIC` guard already used for font widths, CID displacements, and vertical metrics, and applies it to numeric `TJ` adjustment operands.

## Red-First Evidence

Before the source change, the new focused case failed on the accepted parser because a finite `-200000` `TJ` adjustment produced a false visible gap:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 494 assertions, 1 failures`

Failing assertion:

- Expected text lines: `["ABCD", "EFGH"]`
- Actual text lines: `["AB CD", "EFGH"]`

## Implementation

- `PdfTextExtractor::textArrayElements()` now bounds numeric `TJ` adjustment operands with `finiteFontAdvanceMetric()` before emitting adjustment elements.
- Normal finite positive/negative `TJ` adjustments inside the accepted bound still flow through existing text-end and bbox logic.
- Overlarge finite operands are ignored like non-finite adjustment operands, preventing malformed content streams from inserting false spaces or expanding native styled bboxes.

## Focused Evidence

After the source change:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
- Result: `1 test files, 510 assertions, 0 failures`

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-font-width-huge-tj-adjustment-currentbase.php`
- Emits `visible_text="ABCD\nEFGH"`, `huge_finite_tj_adjustment_rejected=true`, `false_word_gap_excluded=true`, `styled_bboxes_preserved=true`, `bbox_numbers_finite=true`, `max_bbox_magnitude=48`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-width-huge-tj-adjustment-currentbase.php` => no syntax errors.
- `jq empty lanes/markerpdf/lane-status.json` => valid JSON.
- `git diff --check -- lanes/markerpdf` => clean.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF content tokenizer, text-array parser, font-width metric guard, styled text bbox builder, and WordPress smoke path. GPU/model OCR, pdftext/PDFium execution, Poppler, Ghostscript, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap And Next Task

This does not overlap the accepted malformed CMap dangling filter-name slice, stream-filter boundary work, CMap source-width fallbacks, CID `/W`/`W2` width metrics, or Type3 FontMatrix/CharProc width slices. The next non-overlapping font-width target should stay in native text positioning and width/CMap boundaries, for example nested text-state resets or additional source-width precedence cases not already covered by the current font-width advance file.
