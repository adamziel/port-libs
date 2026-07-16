# MarkerPDF font width advance horizontal scale boundary current base

- Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T063145Z`
- Base accepted HEAD: `b7a3a2ed54594b1a7fac2631fb027e07cd97f1a6`
- Scope: native searchable-PDF text/font advance behavior only. No OCR, Surya/Texify/Torch, GPU/model execution, Streamlit/FastAPI workers, external PDF tools, or upstream model benchmark parity.

## Behavior

PDF `Tz` horizontal text-scale operands feed text advance and styled-span bbox width. The current-base parser already bounded font size, font widths, Type0 W/W2 metrics, and `TJ` numeric adjustments, but finite pathological `Tz` operands could still push current advance far outside the page geometry and create false WordPress word gaps.

This patch routes `Tz` through the same bounded font-advance metric guard before updating text state. Ordinary finite positive/negative horizontal scale behavior remains covered by the existing negative `Tz` test; overlarge finite positive and negative values are ignored before `Tj`/`TJ` text advance and styled bbox calculation.

## Evidence

Red-first focused run before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 523 assertions / 1 failures`. The new case expected `['ABCD', 'EFGH']` but the negative overlarge `Tz` line produced a false `EF GH` word gap.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files / 539 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-tz-boundary-currentbase.php`

Result: exit 0 with `overlarge_positive_tz_rejected=true`, `overlarge_negative_tz_rejected=true`, `false_word_gap_excluded=true`, `styled_bboxes_preserved=true`, `bbox_numbers_finite=true`, `max_bbox_magnitude=48`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `lanes/markerpdf/lane-status.json` moves `phpPass` from `2433` to `2434`.
- `lanes/markerpdf/lane-status.json` moves `wordpressScenarios` from `2074` to `2075`.
- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` moves `pdfFontWidthAdvanceBoundaryCurrentBaseBehaviors` and `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors` from `3` to `4`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF content tokenizer, text-state operator handling, font advance metric guard, and styled-span bbox extraction already present under `lanes/markerpdf/src`.

## Non-Overlap

This does not repeat the accepted direct page resource dictionary tail boundary, huge finite `/Widths`, huge finite `Tf`, non-finite or huge finite `TJ` adjustment, ordinary negative `Tz`, Type0 W/W2, Type3 FontMatrix, CMap source-width fallback, stream/xref repair, image/filter metadata, annotations, forms, table/equation supplied-boundary, or OCR/model surfaces.

## Next Task

Continue native no-GPU markerPDF import fidelity around remaining font encoding/advance edges, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
