# markerPDF Font Width Quote Spacing Current Base

Session: `port-dev-markerpdf-font-width-advance-20260603T090446Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260603T090446Z`

Base accepted HEAD: `a934fd3337210e4ce0a15739eef0bd11ba3529ba`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF geometry to `pdftext.extraction.dictionary_output(..., keep_chars=False, ...)` and maps returned spans into Marker `Span`, `Line`, and `Block` objects. The native PHP fallback therefore must preserve text-state font advance geometry for WordPress review spans without launching Python, pdftext, pypdfium2/PDFium, OCR, GPU, or model workers.

Relevant parser/dependency behavior is PDF text-showing operator state: the double-quote operator sets word spacing and character spacing, moves to the next line, then shows the text. The existing plain-text path already applied those quote operands before text-advance gap decisions; this slice applies the same state to native styled-span bbox advance.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Native Behavior Added

`PdfTextExtractor::textSpanLinesFromContentStream()` now applies `"` operator word and character spacing operands before calling `appendNativeTextSpan()`. That makes styled-span bboxes match the same font advance state used by `extractTextLines()` and `naiveGetText()`.

The focused PDF uses:

- a Type1 subset simple font with explicit 1000-unit widths for source codes 32 through 66;
- content stream `24 6 (A B) "` after a leading line;
- expected styled span bbox width `72pt`: three 12pt glyph advances plus two 6pt character-spacing advances plus one 24pt word-spacing advance.

Before the source fix, the styled span bbox was `[0,0,36,12]`, because quote word/character spacing was ignored. After the fix, it is `[0,0,72,12]` while text extraction remains `Lead` and `A B`.

## Evidence

Red-first focused test on current base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
FAIL applies quote operator spacing before styled font advance bboxes on current base (lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 0.0,
  1 => 0.0,
  2 => 72.0,
  3 => 12.0,
)
Actual: array (
  0 => 0.0,
  1 => 0.0,
  2 => 36.0,
  3 => 12.0,
)
1 test files, 17 assertions, 1 failures
```

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 662 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `average_width_preserves_joined_word=true`, `generic_500_width_gap_excluded=true`, `quote_operator_text_lines_preserved=true`, `quote_operator_spacing_styled_bbox_preserved=true`, `quote_span_bbox=[0,0,72,12]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock`, `Blo ck`, `Lead`, and `A B`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

All passed.

Whitespace gate:

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `1000 -> 1001`.
- Focused direct test assertions: `11 -> 21`.
- Focused family verification: `3 files / 662 assertions / 0 failures`.
- Mapped font-width advance current-base behaviors: `1 -> 2`.
- WordPress scenarios: `1000 -> 1001`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF content tokenizer, text-showing operator parser, simple-font width metrics, quote-operator text-state helpers, styled-span bbox path, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.

## Non-Overlap

This does not repeat the accepted simple-font positive-width average fallback, Base14 widths, direct/indirect simple-font Encoding Differences, indirect `/FirstChar` or `/Widths`, direct `/MissingWidth`, Type3 CharProc widths, CIDFont `/W`/`/DW`/`W2`, CIDSet/default CIDFont grouping, Type0 Encoding CMap CID width priority, source-space word-spacing, zero-padded ToUnicode source segmentation, styled-span CID resource width bboxes, TJ numeric adjustments, or plain-text quote operator word-spacing decisions. The new boundary is specifically quote-operator word and character spacing feeding native styled-span bbox advance before current-base WordPress review geometry.
