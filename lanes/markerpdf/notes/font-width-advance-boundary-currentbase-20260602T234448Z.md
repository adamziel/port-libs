# markerPDF Font Width Advance Boundary Current Base

Session: `port-dev-markerpdf-font-width-advance-20260602T234448Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260602T234448Z`

Base accepted HEAD: `7daebccdb1e231332676891328ab6455e928870a`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level searchable-PDF text geometry to `pdftext.extraction.dictionary_output(..., keep_chars=False, ...)` before converting dictionaries into Marker `Span`, `Line`, and `Block` objects. The native no-GPU PHP fallback therefore has to preserve PDF font advance boundaries before WordPress paragraph grouping without running Python, pdftext, pypdfium, or models.

Relevant dependency behavior is simple-font width-map construction in pypdf: for a simple font with `/Widths`, `/MissingWidth` wins when present; otherwise the default width for missing codes is the average of positive declared widths, not a hard-coded generic fallback. This slice maps that boundary for the native PHP text advance path.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Native Behavior Added

`PdfTextExtractor::fontWidthMetrics()` now computes a simple-font default advance from the average of positive `/Widths` entries when `/FontDescriptor /MissingWidth` is absent. Explicit widths still win for declared codes, `/MissingWidth` remains authoritative, and zero-width entries do not pull the average down.

The focused PDF uses:

- a Type1 subset font with `/Encoding /Differences` mapping source codes to `WideBlock`;
- `/FirstChar 33`, an intentionally undersized `/Widths [1000 1000 1000 0]`, and no `/MissingWidth`;
- absolute `Tm` positioning that only joins `Wide` plus `Block` when missing codes use the positive-width average `1000` instead of the previous generic `500`;
- a second line that still proves a real positioned word gap survives.

Before the fix, the extractor advanced missing codes at `500`, so the first line inserted a false `Wide Block` gap. After the fix, the first line emits `WideBlock`, while the intentionally separated second line emits `Blo ck`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
1 test files, 11 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `average_width_preserves_joined_word=true`, `generic_500_width_gap_excluded=true`, `narrow_positioned_gap_still_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs for `WideBlock` and `Blo ck`.

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

- Behavior tests: `974 -> 975`.
- Focused new assertions: `11`.
- Mapped upstream/dependency semantics: `681 / 78 -> 682 / 78`.
- WordPress scenarios: `974 -> 975`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, simple-font Encoding Differences parser, simple-font width parser, text-position advance estimator, styled-span bbox path, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted Base14 widths, direct/indirect simple-font Encoding Differences, indirect `/FirstChar` or `/Widths` entries, direct `/MissingWidth`, Type3 CharProc widths, CIDFont `/W`/`/DW`/`W2`, CIDSet/default CIDFont grouping, Type0 Encoding CMap CID width priority, source-space word-spacing, zero-padded ToUnicode source segmentation, or styled-span CID resource width bboxes. The new boundary is specifically the simple-font positive `/Widths` average used as the default advance for missing glyph codes before current-base WordPress text grouping.
