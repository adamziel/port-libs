# markerPDF Font Width Advance Vertical Styled BBox Current Base

Session: `port-dev-markerpdf-font-width-advance-20260603T223504Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260603T223504Z`

Base accepted HEAD: `37107328aa2e26664de5326c647d0bffd6b7b48c`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable PDF text through `pdftext.extraction.dictionary_output` before Marker converts page dictionaries into spans, lines, blocks, and Markdown. The no-GPU PHP fallback therefore needs to keep native span geometry consistent with the same font advance data used for text grouping when pdftext, pypdfium/PDFium, OCR, and model workers are unavailable.

The relevant parser/dependency behavior is PDF Type0 vertical writing: the Type0 `/Encoding` CMap can set `/WMode 1`, descendant CIDFont `/W2` entries supply vertical displacements by CID, and those displacements inform text geometry before WordPress review spans are converted.

## Behavior Added

`PdfTextExtractor::appendNativeTextSpan()` now routes bbox construction through a native bbox helper. Horizontal spans keep the existing `/Widths`, `/W`, `Tc`, `Tw`, `Tz`, `TJ`, and quote-operator advance path. Vertical Type0 spans with a source operand now use `advanceTextEndYForOperand()` so `/W2` and `/DW2` displacements drive bbox height instead of falling back to decoded text length times a horizontal 0.5 advance ratio.

The focused PDF fixture uses a vertical Type0 font with `/WMode 1`, a descendant CIDFont `/W2 [40 43 -500 500 880 50 55 -250 500 880]`, and two text operators for `Vert` and `Import`. Before the fix, styled bboxes were horizontal fallback estimates: `[[0,0,24,12],[24,0,60,12]]`. After the fix, they preserve vertical advance geometry: `[[0,0,12,24],[12,0,24,18]]`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
FAIL uses vertical CIDFont W2 advances for native styled span bboxes on current base
Expected: [[0,0,12,24],[12,0,24,18]]
Actual: [[0,0,24,12],[24,0,60,12]]
1 test files, 27 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 32 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `vertical_w2_text_line_preserved=true`, `vertical_w2_styled_bboxes_preserved=true`, `vertical_horizontal_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock`, `Blo ck`, `Lead`, `A B`, and `VertImport`.

## Status Delta

- `phpPass`: `1029 -> 1030`
- `wordpressScenarios`: `1029 -> 1030`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `2 -> 3`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted simple-font average positive width fallback, quote-operator horizontal styled-span advance, horizontal Type0 CID resource spacing, direct/indirect `/W` parsing, vertical plain-text `/W2` grouping, CIDSet vertical default displacement, predefined vertical CMap detection, or pdftext dictionary sorting. The new boundary is specifically vertical Type0 `/W2` advance geometry for native styled-span bboxes.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, CIDFont `/W2` metric parser, text-state advance helpers, styled-span extraction path, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
