# markerPDF Font Width Advance TJ Inter-Element Spacing Current Base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260606T071208Z`

Base accepted HEAD: `2c3874187ed49e9686f363014a3a498e09dbcd73`

## Source Truth

The pinned markerPDF conversion path delegates searchable-PDF text extraction to parser/pdftext-style layers before OCR/model fallback. In the native no-GPU PHP lane, the PDF text-state boundary matters for WordPress import because `TJ` arrays can split one visible word across multiple source strings while `Tc`, `Tw`, and numeric adjustments still move the current text position between those strings.

PDF text showing semantics apply text-state spacing as the text position advances through a `TJ` array. The drawn glyph bbox should not include the terminal spacing after the final glyph, but the next string element inside the same `TJ` array must start after the previous string's terminal character/word spacing and any numeric adjustment.

## Implementation

- `PdfTextExtractor::textOperandHorizontalDrawnEndX()` now advances the internal `TJ` array cursor through terminal character/word spacing after each text element before placing later text elements.
- `PdfTextExtractor::textOperandHorizontalExtentWidth()` uses the same cursor rule for native styled-span bboxes.
- `PdfTextExtractor::textHorizontalScaleOperand()` now reuses the existing bounded advance guard so overlarge finite `Tz` values fail closed to the previous safe text scale instead of exploding styled bboxes.
- The focused fixture covers separate `TJ` text elements, source-space `Tw`, combined `Tc`/`Tw`, and a numeric adjustment between text elements.

## Focused Evidence

Red-first probe on the accepted base showed `[(A)(B)] TJ` with `6 Tc` returned a styled bbox width of `24.0`; the corrected geometry is `30.0`.

Commands:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result: `1 test files, 559 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfFont*CurrentBaseTest.php
```

Result: `66 test files, 1145 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: emitted `tj_inter_element_spacing_lines_preserved=true`, `tj_inter_element_tc_bbox_preserved=true`, `tj_inter_element_tw_bbox_preserved=true`, `tj_inter_element_tc_tw_bbox_preserved=true`, `tj_inter_element_adjustment_bbox_preserved=true`, `tj_inter_element_collapsed_tc_bbox_excluded=true`, `tj_inter_element_collapsed_tw_bbox_excluded=true`, `tj_inter_element_collapsed_adjustment_bbox_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat Type3 scalar `/Widths` FontMatrix normalization, Type3 CharProc FontMatrix vector advances, CID vertical `/W2`, text rise, TJ backtracking without inter-element spacing, relative `Td`, absolute `Tm`, xref repair, metadata, object streams, images, annotations, forms, tables, OCR, or model execution. The bounded behavior is horizontal `TJ` array inter-element spacing and bounded `Tz` overflow protection inside the existing font-width advance cluster.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, content-token parser, simple-font width metrics, text-state spacing, styled span extraction, and WordPress smoke path. GPU/OCR/model execution and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
