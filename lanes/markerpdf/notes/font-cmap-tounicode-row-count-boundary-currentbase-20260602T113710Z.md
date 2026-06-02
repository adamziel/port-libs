# markerPDF Font CMap ToUnicode Row-Count Boundary

Session: `port-dev-markerpdf-cmap5pdf-20260602T113710Z`
Micro-slice: `font-cmap-tounicode-edge-boundary-currentbase-20260602T113710Z`
Base accepted HEAD: `56aa52b69149689c8c9de7b63a5b3c68b21f52b3`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output`, then converts pdftext dictionaries into Marker `Span`, `Line`, and `Block` objects. The native PHP fallback keeps that boundary by fixing the PDF font CMap decoding path before WordPress paragraphs are emitted.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/pyproject.toml
- https://pypi.org/project/pdftext/0.3.18/
- https://pypdf.readthedocs.io/en/stable/dev/cmaps.html

The relevant CMap syntax is count-prefixed mapping blocks such as `1 beginbfchar` and `1 beginbfrange`. The count bounds the mapping rows in that block; extra malformed rows before the matching end operator must not become live Unicode mappings.

## Native Behavior Added

`PdfTextExtractor::parseToUnicodeCMap()` now parses ToUnicode `beginbfchar` and `beginbfrange` blocks through a count-aware CMap block helper. Declared counts limit parsed mapping rows for both scalar range rows and array destination range rows.

Before this slice, a malformed CMap with `1 beginbfchar` followed by two `<src> <dst>` rows mapped both rows, causing fake text `Leak` to override unmapped source byte fallback. The same happened for `1 beginbfrange`, where an extra range row produced `WFG` instead of preserving fallback bytes `WQR`. After the fix, only declared mapping rows are live, and extra rows stay inert.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL honors declared ToUnicode bfchar and bfrange row counts before WordPress text
Expected: 'Import B
WQR'
Actual: 'Import Leak
WFG'

1 test files, 525 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 528 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-tounicode-row-count-boundary.php
```

The smoke emits `paragraphs=["Import B","WQR"]`, `declared_bfchar_count_honored=true`, `declared_bfrange_count_honored=true`, `extra_mapping_rows_ignored=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `487 -> 488`.
- Mapped semantics: `335 -> 336 / 78`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, stream decoder, ToUnicode CMap parser, source-code segmentation, and WordPress paragraph smoke path. Full upstream markerPDF runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya, tabled-pdf, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

## Non-Overlap

This does not repeat accepted ToUnicode usecmap cycle/codespace guards, CMap comment stripping, bfrange destination-array parsing, ToUnicode source-width fallback, malformed ToUnicode filter fallback, Type0 `/Encoding` CMap boundaries, CIDFont decimal `/W` parsing, or bidi/surrogate glyph-advance grouping. The new behavior is limited to declared row-count boundaries inside ToUnicode `bfchar` and `bfrange` mapping blocks.
