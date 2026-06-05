# markerPDF Font Width Advance Text Matrix Vertical Scale Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T003813Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T003813Z`

Base accepted HEAD: `547037c192ab015b7b147821804623f6ff376004`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is text-matrix vertical scale in native styled-span geometry. Searchable PDF text extraction already uses font widths, text state spacing, horizontal text-matrix scale, and vertical CIDFont `/W2` advances for grouping and bbox dimensions. The missing edge was a horizontal-writing text matrix such as `1 0 0 0.5 ... Tm` or `1 0 0 2 ... Tm`: visible text stayed correct, but native styled bboxes kept an unscaled 12pt height.

## Source Truth

Upstream `sddai/markerPDF` delegates searchable-PDF text geometry to `pdftext.extraction.dictionary_output` before Marker converts page dictionaries into spans, lines, blocks, and Markdown. The native PHP fallback therefore has to preserve PDF text-matrix scale in span metadata before WordPress review/import, even when the visible paragraph text and word-gap decisions do not change.

PDF text matrices carry independent x and y axis scales. The existing native path already consumed the x-axis scale for horizontal advance; this slice adds the y-axis magnitude for styled-span bbox height.

## Native Behavior Added

`PdfTextExtractor::textSpanLinesFromContentStream()` now tracks the current text matrix vertical axis scale from `Tm` operands and passes it into `appendNativeTextSpan()`. `nativeTextSpanBbox()` applies that scale to span height while keeping the existing width advance, `Td`/`Tm` word-gap, `TJ`, quote-operator, and vertical `/W2` paths unchanged.

The focused PDF fixture uses:

- a simple Type1 font with 1000-unit widths;
- a first `Tm` with vertical scale `0.5` for `AB`;
- a second same-line `Tm` with vertical scale `2` for `CD`;
- identical text positioning that should still emit one visible paragraph, `ABCD`, without a false `AB CD` gap.

Before the fix, styled bboxes were unscaled: `[[0,0,24,12],[24,0,48,12]]`. After the fix, they preserve text-matrix y scale: `[[0,0,24,6],[24,0,48,24]]`.

## Evidence

Red-first focused check on the accepted base after adding the test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
PASS uses scaled text matrix advance before relative Td word-gap decisions on current base
FAIL uses text matrix vertical scale before native styled span bboxes on current base
Expected: [[0,0,24,6],[24,0,48,24]]
Actual: [[0,0,24,12],[24,0,48,12]]
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 73 assertions, 1 failures
```

Focused passing gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
PASS uses scaled text matrix advance before relative Td word-gap decisions on current base
PASS uses text matrix vertical scale before native styled span bboxes on current base
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 78 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `text_matrix_vertical_scale_span_bboxes_preserved=true`, `text_matrix_vertical_unscaled_height_excluded=true`, `text_matrix_vertical_scale_false_gap_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by a Gutenberg paragraph for `ABCD`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1192 -> 1193`.
- `wordpressScenarios`: `1175 -> 1176`.
- Manifest mapped behaviors: `714 -> 715`.
- Focused file assertions: `67 -> 78`.
- Focused new assertions: `11`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, simple-font width metrics, text-state advance helpers, styled-span bbox path, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted simple-font average positive width fallback, quote-operator spacing, relative `Td` current-font advance, text-matrix horizontal scale for word-gap decisions, unresolved width-array slot preservation, vertical Type0 `/W2` styled bboxes, page graphics-state `cm` text-position transforms, Form XObject `/Matrix` boundaries, or Image XObject graphics-state review. The new boundary is specifically horizontal-writing styled-span bbox height from text-matrix vertical axis scale.
