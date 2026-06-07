# Font Width Advance q/Q Graphics-State Restore Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260607T023501Z`
Base: `05bed73f19639964f52771715b94760db8c6fd1b`

## Behavior

Native searchable-PDF text extraction now restores the text-matrix horizontal
scale, horizontal extent scale, horizontal advance scale, and styled-span
vertical scale when a content stream closes a `q`/`Q` graphics-state scope.
This aligns line/plain/styled extraction with the existing text-run path before
decoding `TJ` adjustment gaps.

The focused fixture keeps an inner half-scale `Tm` inside `q`, emits `AB` with a
negative `TJ` adjustment, closes `Q`, then emits `C D` with another `TJ`
adjustment. Before this slice, line/plain/styled extraction kept the stale
inner half-scale after `Q`, so the final gap collapsed to `ABCD` and the second
styled span bbox stopped at `42`. After the fix, WordPress-visible text is
`ABC D` and styled bboxes are `[0,0,21,12]` then `[21,0,63,12]`.

## Evidence

- Baseline focused file before the new case:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  -> `1 test files / 595 assertions / 0 failures`.
- Red-first focused run after adding the new case and before the source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  -> `FAIL restores q/Q text matrix advance scale before TJ gaps on current base`;
  expected `['ABC D']`, actual `['ABCD']`; `1 test files / 596 assertions /
  1 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  -> `1 test files / 607 assertions / 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`
  reports `graphics_state_restore_tj_gap_preserved=true`,
  `graphics_state_restore_run_gap_preserved=true`,
  `graphics_state_restore_styled_bboxes_preserved=true`,
  `graphics_state_restore_stale_inner_scale_excluded=true`,
  `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2744 -> 2745`
- focused behavior assertions in the existing boundary file: `595 -> 607`
- `wordpressScenarios`: `2312 -> 2313`
- mapped manifest behavior:
  `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors 3 -> 4`

## Non-Overlap

This stays in native font-width/TJ positioning and does not overlap accepted
OCR/model work, stream-filter/xref repair, object-stream annotation review,
Type3 FontMatrix vector fallback, vertical W2 geometry, text-object BT/ET
reset, rotated text-matrix vector gaps, or supplied-boundary table/equation
handoffs.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP content
tokenizer, font-width decoder, text-run extractor, line extractor, styled-span
bbox path, and WordPress smoke harness. GPU/model execution, OCR, Surya,
Texify, Torch, Streamlit/FastAPI workers, and external PDF tools remain
intentionally out of scope for this no-GPU markerPDF lane.
