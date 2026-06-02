# markerPDF Type0 CMap Indirect Width Differences

Session: `port-dev-markerpdf-font24pdf-20260602T1528Z`
Micro-slice: `font-type0-cmap-width-differences-currentbase-20260602T1528Z`
Base accepted HEAD: `8cb0fd3a8ba68fd497a72127b48fd7a38e02290c`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text geometry to `pdftext.extraction.dictionary_output` before Marker converts pdftext dictionaries into page `Span`, `Line`, and `Block` objects.

Relevant dependency behavior is pypdf CMap/font parsing: `build_char_map_from_dict()` builds a font width map, descends through `/DescendantFonts[0]`, dereferences `/DW` and `/W` with `get_object()`, and applies CIDFont glyph metrics before text spacing decisions. This native reduced boundary mirrors that for the PHP fallback without running Python, pdftext, pypdfium, or model workers.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Native Behavior Added

`PdfTextExtractor::fontWidthMetrics()` now resolves horizontal CIDFont `/W` arrays through indirect objects, matching the object-aware handling already used by simple-font `/Widths` and vertical CIDFont `/W2`.

The focused PDF uses a Type0 font with:

- an indirect `/Encoding` CMap whose `begincidchar` rows map source bytes to descendant CIDs;
- a descendant CIDFont with `/DW 500` plus indirect `/W 7 0 R`;
- wide CID widths for `WideBlock` and narrow CID range widths for `Thin Text`.

Before the fix, the indirect `/W` array was ignored, so `/DW 500` controlled both lines and the extractor emitted `Wide Block` and `ThinText`. After the fix, the indirect `/W` differences drive text-advance grouping and WordPress paragraphs emit `WideBlock` plus `Thin Text`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL resolves indirect Type0 CIDFont W width differences before WordPress text gaps
Expected: ['WideBlock', 'Thin Text']
Actual: ['Wide Block', 'ThinText']
1 test files, 565 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 569 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-indirect-w-widths.php
```

The smoke emits `WideBlock`, `Thin Text`, `resolves_indirect_w_array=true`, `wide_cid_widths_preserve_joined_word=true`, `narrow_cid_widths_preserve_word_gap=true`, `default_width_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, Type0 CMap parser, ToUnicode CMap parser, descendant CIDFont metric parser, text-position grouping path, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling.

## Non-Overlap

This does not repeat accepted Type0 Encoding CMap CID width priority, mixed code-space no-ToUnicode fallback, decimal direct `/W` parsing, vertical `/W2` indirect metrics, CIDSet/default-width grouping, simple-font Encoding Differences, indirect simple-font `/FirstChar`/`/Widths`, or bidi/surrogate source-width boundaries. The new boundary is specifically horizontal CIDFont `/W` width differences supplied through an indirect object and selected through a Type0 `/Encoding` CMap before WordPress text-gap grouping.
