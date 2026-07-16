# markerPDF Font Width Scaled Td Advance Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T000253Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T000253Z`

Base accepted HEAD: `5aed9352dde56e9d01a0adb0d493e315086b3bd4`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit delegates searchable-PDF text extraction to `pdftext.extraction.dictionary_output` before Marker converts PDF dictionaries into spans, lines, blocks, and Markdown. The native no-GPU PHP fallback must therefore preserve low-level PDF text-state geometry before WordPress paragraph grouping when pdftext, PDFium, OCR, and model execution are intentionally unavailable.

The relevant PDF parser boundary is text matrix movement: relative `Td` x movement is in text space and must be transformed by the current text matrix before comparing the next text position against the current font-width end position. If the current text matrix is half-scaled, `24 0 Td` advances by 12 user units, not 24.

## Behavior Added

`PdfTextExtractor::textMoveX()` and the matching word-gap helper now apply the active text matrix horizontal scale to relative `Td` x movement. Existing glyph advances still come from the active font width map, `Tz`, quote spacing, `TJ`, and vertical `/W2` paths.

The focused fixture adds:

- a simple Type1 font with 1000-unit widths for `A` through `D`;
- `0.5 0 0 1 72 720 Tm <4142> Tj 24 0 Td <4344> Tj`, which should stay `ABCD`;
- a second half-scaled line with `48 0 Td`, which should still preserve the real positioned gap as `AB CD`;
- styled-span bbox checks proving the first line remains two 12pt-wide spans.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
FAIL uses scaled text matrix advance before relative Td word-gap decisions on current base (lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php)
Expected: ['ABCD', 'AB CD']
Actual: ['AB CD', 'AB CD']
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 56 assertions, 1 failures
```

Passing focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 67 assertions, 0 failures
```

Adjacent font/CMap advance gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php
5 test files, 148 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `scaled_td_uses_text_matrix_current_end=true`, `scaled_td_false_gap_excluded=true`, `scaled_td_span_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `ABCD` and `AB CD`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1154 -> 1155`
- `wordpressScenarios`: `1143 -> 1144`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused assertion count for `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `55 -> 67`

## Non-Overlap

This does not repeat accepted simple-font positive-width averaging, quote-operator styled bbox spacing, unscaled relative `Td` current-font advance, unresolved simple-font width slots, vertical Type0 `/W2` styled bboxes, Type0 CID resource spacing, Type3 CMap spacing, `TJ` numeric adjustments, or CMap source-width fallback. The new boundary is specifically scaled text-matrix `Td` x movement before native WordPress word-gap decisions.

## Dependency Closure

No new support component is needed. This patch reuses the native content tokenizer, text-state operator parser, font width metric path, positioned text grouping, styled-span extraction, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF directive.
