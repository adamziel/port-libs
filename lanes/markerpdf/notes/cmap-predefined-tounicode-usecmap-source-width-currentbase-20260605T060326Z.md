# markerPDF CMap predefined ToUnicode usecmap source-width fallback

Session: `port-dev-markerpdf-source-width-20260605T060326Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T060326Z`
Base accepted HEAD: `e52793fde5f02e1281af42ed0ed1df5107454746`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py`, delegating font, CMap, and glyph decoding to pdftext/PDFium before Markdown/WordPress assembly.

The native no-GPU PHP fallback therefore has to preserve CMap source-code boundaries before text and span-width grouping when Python, pdftext, PDFium, and model workers are unavailable. A ToUnicode CMap may inherit source-code metadata with `usecmap`; when the inherited CMap is a predefined Identity/UCS2 CMap rather than an embedded named stream, its code-space width still determines how source bytes such as `<0041>` are segmented before fallback Unicode decoding and CIDFont width lookup.

## Implementation

`PdfTextExtractor::parseToUnicodeCMap()` now falls back to predefined Identity-H, Identity-V, and UCS2 CMap metadata when a ToUnicode `usecmap` name is not present in the embedded named-CMap registry. The fallback carries code-space ranges and writing mode only; it does not fabricate explicit Unicode mappings.

This prevents a damaged Type0 font with `/Encoding /MissingCustom-H` and `/ToUnicode` containing `/Identity-H usecmap` from falling back to raw NUL-separated bytes. The repaired path decodes `<0041004200430044>` as `ABCD`, keeps source keys at `0041` through `0044`, and applies descendant CIDFont `/W` widths before WordPress paragraph gaps and styled-span bboxes.

## Red / Green Evidence

Red-first focused check after adding the test and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
FAIL inherits predefined ToUnicode usecmap source widths before raw byte fallback on current base
Expected: array (
  0 => 'ABCD EFGH',
)
Actual: array (
  0 => '' . "\0" . 'A' . "\0" . 'B' . "\0" . 'C' . "\0" . 'D' . "\0" . 'E' . "\0" . 'F' . "\0" . 'G' . "\0" . 'H',
)
1 test files, 155 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
PASS inherits predefined ToUnicode usecmap source widths before raw byte fallback on current base
1 test files, 164 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke exits 0 and reports `predefined_tounicode_usecmap_source_widths_applied=true`, `predefined_tounicode_usecmap_runs_preserved=true`, `predefined_tounicode_usecmap_raw_bytes_excluded=true`, `predefined_tounicode_usecmap_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Type0 `/Encoding /Identity-H` or `/UniJIS-UCS2-H` fallback, named embedded ToUnicode `usecmap` inheritance, Type0 Encoding CMap `/UseCMap` width grouping, metric-miss ToUnicode fallback, partial metric-miss chunk fallback, horizontal/vertical `TJ` adjustment gaps, odd-hex padding, repeated zero padding, explicit longer source-key precedence, malformed mixed-width `bfrange` rejection, indirect `/W`/`DW`/`W2`, CIDSet grouping, Type3 CMap width grouping, xref repair, parser stream boundaries, metadata, annotations, forms, or image/filter review.

The bounded behavior is specifically predefined Identity/UCS2 `usecmap` inheritance inside ToUnicode CMaps before raw-byte fallback and CIDFont source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, predefined CMap metadata, text operand source-key segmentation, CIDFont width parser, styled-span extractor, and WordPress smoke renderer.

Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
