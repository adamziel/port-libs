# Font Width Horizontal Scale Advance Boundary Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T075432Z`
Base: `49625b9af9c7161add8ed9e16be8b88592c4d6bc`
Date: 2026-06-06 UTC

## Source Truth

The upstream markerPDF searchable-PDF path depends on pdftext-style text extraction before the no-GPU PHP lane creates WordPress paragraphs and styled span review metadata. PDF text state `Tz` is horizontal scaling for text-space advances; malformed or extreme finite values must not let a searchable PDF create false word gaps or oversized review boxes during import.

This current-base slice covers the already-focused overlarge finite `Tz` boundary in the native PHP extractor. Before the fix, a negative `-1000000 Tz` operand was accepted into the advance path and split the safe second line as `EF GH`.

## Implementation

- `PdfTextExtractor::textHorizontalScaleOperand()` now routes `Tz` operands through `finiteFontAdvanceMetric()`.
- Normal negative horizontal scale behavior remains covered by the existing `-100 Tz` case.
- Overlarge positive and negative horizontal-scale values are ignored before plain text line grouping, text-run grouping, styled span bbox generation, and WordPress smoke rendering.

## Evidence

Red-first before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 543 assertions, 1 failures`; the overlarge finite `Tz` case produced `EF GH` instead of `EFGH`.

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result: `1 test files, 559 assertions, 0 failures`.

Adjacent font/CMap width run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php`

Result: `5 test files, 969 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-tz-boundary-currentbase.php`

Result: emitted `overlarge_positive_tz_rejected=true`, `overlarge_negative_tz_rejected=true`, `false_word_gap_excluded=true`, `styled_bboxes_preserved=true`, `bbox_numbers_finite=true`, `max_bbox_magnitude=48`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused PHP behavior cases: `+1` (`phpPass` 2457 -> 2458).
- WordPress smoke scenarios: `+1` (`wordpressScenarios` 2095 -> 2096).
- Mapped upstream denominator: unchanged; this stays inside the existing native PDF font-width/text-state behavior cluster.

## Non-Overlap

This does not repeat Type3 CharProc FontMatrix vector advances, Type3 scalar `/Widths` normalization, CID `/W` or `/W2` metrics, simple-font malformed `/Widths`, CMap source-width fallback, `TJ` adjustment overflow, quote operator spacing, relative `Td` gaps, rotated text matrices, or malformed CMap/filter parser boundaries. It is limited to `Tz` horizontal-scale admission before current font-width advance and styled bbox calculations.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF content tokenizer, text-state parser, font-width metric lookup, advance calculator, styled span extraction, and WordPress smoke path. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext runner parity, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
