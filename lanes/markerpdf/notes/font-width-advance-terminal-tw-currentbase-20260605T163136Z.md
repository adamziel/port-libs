# markerPDF Font Width Advance Terminal Tw Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T163136Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T163136Z`

Base: `d35739fb1421e2e65d55da1e5c9f8fc25164043c`

## Source Truth

The pinned upstream markerPDF path routes searchable PDF text through `pdftext.extraction.dictionary_output()` and then preserves each pdftext span `text`, `bbox`, font, weight, and size in `marker/pdf/extract_text.py::pdftext_format_to_blocks`. The no-GPU PHP fallback therefore needs to keep text cursor advancement and styled drawn bboxes separate when PDF text spacing operators advance the cursor beyond visible glyph extents.

The PDF text-state boundary for this slice is terminal source-space `Tw`: word spacing after a final source space contributes to the current text cursor, but it must not enlarge the styled drawn bbox used for subsequent absolute `Tm` gap geometry.

## Behavior

`PdfTextExtractor::textElementHorizontalExtent()` now derives styled horizontal min/max extents from glyph endpoints rather than seeding the bbox extrema with the final cursor position. Current cursor advancement still uses `advanceTextEndX()`, so terminal `Tw` remains available to relative/absolute text positioning decisions while styled span bboxes stop at the drawn source-space advance.

The focused fixture uses `24 Tw`, `(AB ) Tj`, then two absolute `Tm` positions:

- `120 720 Tm (CD) Tj` has a 12-unit gap after the drawn space glyph and preserves styled bboxes `[[0,0,36,12],[48,0,72,12]]`.
- `114 704 Tm (CD) Tj` is below the 12-unit gap threshold and compacts styled bboxes to `[[0,0,36,12],[36,0,60,12]]`.

Visible text remains `AB CD` on both lines because the decoded first span ends with whitespace.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
FAIL keeps terminal word spacing in current advance but out of styled drawn bboxes on current base
Expected: [[0.0,0.0,36.0,12.0],[48.0,0.0,72.0,12.0]]
Actual: [[0.0,0.0,60.0,12.0],[60.0,0.0,84.0,12.0]]
1 test files, 386 assertions, 1 failures
```

After the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 394 assertions, 0 failures
```

## WordPress Smoke

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits:

- `terminal_tw_uses_cursor_advance_for_tm_gap=true`
- `terminal_tw_drawn_bbox_excludes_terminal_word_spacing=true`
- `terminal_tw_subthreshold_gap_compacts_styled_bbox=true`
- `terminal_tw_stale_word_spacing_bbox_excluded=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat accepted terminal `Tc` current-cursor advance, relative/scaled `Td`, quote-operator spacing, absolute `Tm` styled gap preservation, `TJ` backtracking/drawn extent, non-finite width/`TJ` rejection, Type0 `/W`/`W2`, Type3 FontMatrix normalization, CMap source-width fallback, malformed CMap/filter behavior, xref repair, annotations, forms, image filters, metadata, OCR/model execution, or table/equation supplied-boundary behavior.

The new boundary is specifically terminal source-space `Tw` in current text-position advance while excluding that terminal spacing from styled drawn bboxes.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, content-token parser, simple-font width metrics, source-space word-spacing detection, text cursor advance helpers, styled-span bbox construction, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
