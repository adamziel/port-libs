# markerPDF Font Width Relative Td Styled Gap Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T084603Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T084603Z`

Base accepted HEAD: `a798274bd52448982c465ba864213a1ca1ac4eba`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is relative `Td` word-gap geometry for native styled-span review. Plain text already used font-width current-end positions to emit `AB CD` when a relative `Td` jump moved the next text run beyond the previous cursor. The styled-span path still compacted the second span immediately after the first bbox, losing the visual gap that WordPress link/table/review overlays need.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium-backed extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. At that boundary, positioned PDF spans carry geometry from text state and font advance. The native PHP fallback therefore needs `Td` movement to affect styled-span bboxes, not only paragraph text.

For PDF `Td`, the next text matrix is translated from the line start. A same-line positive x move greater than the current text cursor creates a real visual gap. Terminal `Tc` remains part of the cursor advance, so the styled geometry gap is based on current text end, not the drawn glyph extent.

## Implementation

`PdfTextExtractor::textSpanLinesFromContentStream()` now tracks current text cursor/end positions alongside existing styled-span state. When a same-line horizontal `Td` produces a word-gap-sized x jump, the next native span starts at the previous bbox end plus that pending gap. Normal compact placement is preserved when `Td` lands on the current end position, and existing `Tm` compact behavior remains unchanged.

## Red-First Evidence

Before the source change, the new focused fixture decoded paragraph text correctly but compacted styled geometry:

```text
extractTextLines: ["AB CD", "ABCD"]
first styled bboxes: [[0,0,24,12],[24,0,48,12]]
```

After the fix, the first line preserves the relative `Td` review gap while the second no-gap line stays compact:

```text
first styled bboxes: [[0,0,24,12],[48,0,72,12]]
second styled bboxes: [[0,0,24,12],[24,0,48,12]]
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 254 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `relative_td_styled_gap_lines_preserved=true`, `relative_td_styled_gap_bboxes_preserved=true`, `relative_td_styled_gap_line_bbox_preserved=true`, `relative_td_styled_gap_compaction_excluded=true`, `relative_td_styled_no_gap_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks run for this slice:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1636 -> 1637`
- `wordpressScenarios`: `1509 -> 1510`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `21 PASS / 239 assertions -> 22 PASS / 254 assertions`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+15`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, font width map, text-state spacing helpers, `Td` positioning helpers, styled-span extraction path, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal `Tc`, relative/scaled `Td` plain-text gap decisions, text matrix vertical scaling, negative/rotated matrices, `Ts` text rise, horizontal or vertical `TJ` backtracking, unresolved width slots, text object reset, `/LastChar` clipping, malformed width ranges, direct or indirect vertical `/W2` bboxes, indirect `/W` parsing, CMap source-width fallback, Type3 CharProc widths, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight.

The new boundary is specifically native styled-span bbox preservation for a same-line relative `Td` word gap after current font-width cursor advancement.

## Next Task

Continue with native no-GPU searchable-PDF font/CMap boundary work or pivot to another in-scope parser/converter gap around CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
