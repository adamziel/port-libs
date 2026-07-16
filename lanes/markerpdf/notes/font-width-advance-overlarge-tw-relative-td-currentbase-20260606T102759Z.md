# markerPDF Font Width Overlarge Tw Relative Td Current Base

Session: `port-dev-markerpdf-font-width-advance-20260606T102759Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260606T102759Z`

Accepted base: `42fdc6ac8852fb015d719b5c26ba483c909bd979`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is direct PDF `Tw` word-spacing state before a same-line relative `Td` movement. An overlarge finite `Tw` value must not poison the current text position used for styled-span geometry and make WordPress review overlays collapse a real `Td` gap.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through text extraction before conversion into spans, lines, blocks, and Markdown. At this boundary, PDF text-state operators contribute to glyph advances, but nonsensical finite advances must be bounded so one malformed operand cannot overflow the native cursor and erase later positioned geometry.

The native PHP fallback already bounds `Tc`, quote-operator spacing, `Tz`, `Tf`, font widths, and `TJ` adjustments through finite font-advance guards. Direct `Tw` now uses the same boundary before current text advance updates.

## Implementation

`PdfTextExtractor::textWordSpacingOperand()` now passes direct `Tw` operands through `finiteFontAdvanceMetric()` before they are stored in text state. Valid finite word spacing remains available for text cursor advancement, while absurd finite values are rejected before they can affect later relative `Td` styled bboxes.

## Red-First Evidence

Before the source edit, the added fixture preserved visible text but collapsed the second styled span to the first span's drawn end:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
=> 1 test files, 565 assertions, 1 failures
Expected second span bbox [48,0,60,12], actual [24,0,36,12]
```

After the fix, the same fixture preserves clean text and the relative `Td` review gap:

```text
extractTextLines: ["A B"]
styled bboxes: [[0,0,24,12],[48,0,60,12]]
line bbox: [0,0,60,12]
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 570 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontTextStateSpacingAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 28 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php > /tmp/markerpdf-font-width-advance-boundary-currentbase.html
```

The smoke emits `overlarge_tw_relative_td_lines_preserved=true`, `overlarge_tw_relative_td_plain_text_preserved=true`, `overlarge_tw_relative_td_gap_bbox_preserved=true`, `overlarge_tw_relative_td_line_bbox_preserved=true`, `overlarge_tw_relative_td_collapsed_gap_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

Result: clean.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2509 -> 2510`
- `wordpressScenarios`: `2133 -> 2134`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `44 PASS / 565 assertions -> 45 PASS / 570 assertions`
- Focused PASS case delta: `+1`
- Focused assertion delta: `+5`

## Dependency Closure

No new support component is needed. This slice reuses the native content-token parser, PDF text-state spacing handlers, font-width advance guard, styled-span bbox extraction, focused PHP test runner, and existing WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted absolute `Tm` styled gap behavior, normal relative `Td` styled gap behavior, terminal `Tc`, terminal `Tw` drawn bbox exclusion, quote-operator spacing, horizontal scale `Tz` overflow, `TJ` overlarge adjustment boundaries, simple-font width array validation, Type0/CID `/W` or `/W2`, Type3 FontMatrix advances, xref repair, metadata, annotation, form, image, table, equation, OCR, or model behavior.

The new boundary is specifically direct `Tw` word-spacing overflow before later same-line relative `Td` styled-span geometry.

## Next Task

Continue with native no-GPU searchable-PDF font/CMap boundary work or pivot to another in-scope parser/converter gap around CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
