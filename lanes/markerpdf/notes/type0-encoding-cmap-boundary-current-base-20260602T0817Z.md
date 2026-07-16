# markerPDF Type0 Encoding CMap Boundary Slice

Session: `port-dev-markerpdf-fontcmap16-20260602T081217Z`
Micro-slice: `font-cmap-encoding-width-boundaries-20260602T081217Z`
Base accepted HEAD: `94516ca342c58c846d00da6f4db62820066cac41`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF text extraction to `pdftext.extraction.dictionary_output` before Marker assembles page blocks. This native PHP slice keeps that fallback boundary by fixing text extraction before pdftext-style lines feed WordPress paragraph rendering.

PDF/parser source truth: Type0 font `/Encoding` CMaps define source-code code spaces and map those character codes to descendant CIDFont CIDs. Descendant CIDFont `/W` and `/DW` metrics are keyed by CID, so width grouping must segment the original text-showing bytes with the font Encoding CMap before applying CID widths. When no `/ToUnicode` CMap is available, the native fallback should still use the font Encoding CMap code-space boundaries instead of treating mixed-width source bytes as raw one-byte text.

Source references:

- `marker/pdf/extract_text.py` in upstream markerPDF at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `pypdf/_cmap.py::build_font_width_map()` dependency behavior for descendant CIDFont width lookup.
- ISO PDF Type0/CMap/CIDFont behavior documented by the PDF reference.

## Native Behavior Added

`PdfTextExtractor` now creates a fallback map from an indirect Type0 font `/Encoding` CMap when `/ToUnicode` is absent. The fallback carries the Encoding CMap code-space ranges into text decoding, so mixed one-byte and two-byte source codes do not leak NUL bytes into Gutenberg paragraphs.

CID width and vertical displacement lookup now segments source operands with `cidCodeSpaceRanges` and `cidMap` when present. This keeps descendant CIDFont `/W`, `/DW`, `/W2`, `/DW2`, and `/CIDSet` metrics keyed by the Encoding CMap CIDs instead of by raw one-byte chunks.

The focused fixture makes the boundary visible:

- `<0057> <0065>` are two-byte source codes that map to wide CIDs, so `Wide` and `Block` stay joined as `WideBlock`.
- `<42> <74>` are one-byte source codes that map to narrow CIDs, so `Thin Text` keeps its word gap.
- The PDF intentionally omits `/ToUnicode`, proving the fallback comes from the Type0 `/Encoding` CMap.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
FAIL uses Type0 Encoding CMap code-space boundaries for fallback text widths
Actual first line included NUL-byte UTF-16 fragments: "\0W\0i\0d\0eBlock"
1 test files, 441 assertions, 1 failures
```

Passing focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 446 assertions, 0 failures
```

Full markerPDF lane gate:

```text
php tools/run-tests.php lanes/markerpdf/tests
59 test files, 2616 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type0-encoding-cmap-boundary-import.php
```

The smoke emits Gutenberg paragraphs `WideBlock` and `Thin Text` with `no_tounicode_fallback=true`, `uses_encoding_cmap_code_space_boundaries=true`, `nul_bytes_removed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, CMap stream decoder, Type0 font resource parser, descendant CIDFont metric parser, text-position grouping logic, and WordPress paragraph smoke path. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.

## Non-Overlap

This does not repeat the accepted Type0 Encoding CMap CID width-priority slice, Identity-H/V fallback, ToUnicode codespace fallback, CIDFont descriptor default-width slice, simple-font Encoding Differences, Standard/MacRoman/Symbol encoding, or subset glyph-name slices. The new behavior is limited to no-ToUnicode Type0 fonts whose indirect `/Encoding` CMap has mixed source-code widths that must drive fallback decoding and descendant CIDFont width lookup.
