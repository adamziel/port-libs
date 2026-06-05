# markerPDF Font Width Advance Negative Horizontal Scale Td Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T121908Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T121908Z`

Base accepted HEAD: `83833276ce29682e35bfb0292b3d0bc70f094d70`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text geometry to `pdftext.extraction.dictionary_output(..., keep_chars=False, ...)` before Marker span/line/block cleanup. The native no-GPU PHP fallback therefore has to preserve PDF text-state glyph advances and drawn extents before WordPress paragraph grouping without running Python, pdftext, pypdfium, OCR, or models.

Relevant PDF text behavior for this slice: `Tz` sets horizontal text scaling, negative horizontal scaling mirrors glyph advance, `Tj` updates the current text matrix, and `Td` moves the text line matrix. A relative `Td` gap check must not compare only against a mirrored logical cursor that moved left; it must also consider the drawn glyph extent that reaches the next text origin. Terminal `Tc` remains part of the logical cursor advance, so the boundary must use the farthest finite logical/drawn horizontal boundary rather than replacing logical advance with drawn extent.

## Native Behavior Added

`PdfTextExtractor` now routes relative `Td` horizontal word-gap decisions through `horizontalTextGapReferenceEnd()`, which chooses the farthest finite horizontal boundary between the logical text end and the drawn glyph extent.

The focused fixture uses:

- a simple Type1 font with explicit `/Widths [1000 1000 1000 1000]`;
- `-100 Tz` before `<4142> Tj`, so the logical cursor moves left while the drawn glyph extent still reaches the original right edge;
- `0 0 Td <4344> Tj`, which previously compared against the stale left-moving logical cursor and inserted `AB CD`;
- a second positive-scale line that still proves normal relative `Td` joining remains intact.

After the patch, both native text lines are `ABCD`, and styled spans for the mirrored line remain adjacent at `[0,0,24,12]` then `[24,0,48,12]`.

## Evidence

Red-first focused run after adding the failing assertion, before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses negative horizontal scale drawn extent before relative Td gap decisions on current base
1 test files, 328 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses negative horizontal scale drawn extent before relative Td gap decisions on current base
1 test files, 340 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `negative_horizontal_scale_td_false_gap_excluded=true`, `negative_horizontal_scale_td_plain_text_preserved=true`, `negative_horizontal_scale_td_styled_bboxes_preserved=true`, `negative_horizontal_scale_td_stale_logical_end_gap_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `1814 -> 1815`.
- Focused assertions in `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `327 -> 340`.
- WordPress scenarios: `1649 -> 1650`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, simple-font width parser, positioned text operand decoder, text-state advance estimator, styled-span bbox path, and WordPress smoke renderer. GPU/model execution, live OCR, Surya/Texify/Torch workers, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted simple-font average width fallback, quote-operator spacing, terminal `Tc` advance, relative `Td` positive-scale advance, scaled `Tm` relative `Td`, text-rise bboxes, absolute `Tm` gap geometry, `TJ` drawn extent, negative `Tc`, negative text matrix, rotated horizontal-vector bboxes, text-object reset, vertical `/W2`, indirect CID `/W`/`W2`, malformed width ranges, non-finite widths, Type3 FontMatrix widths, Type3 vector advance, or CMap/source-width fallback slices. The new boundary is specifically negative horizontal text scaling (`Tz`) before a same-origin relative `Td` gap decision.
