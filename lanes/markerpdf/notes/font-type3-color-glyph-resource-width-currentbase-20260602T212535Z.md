# markerPDF Type3 color glyph resource width current base

Session: `port-dev-markerpdf-font60-20260602T212535Z`

Micro-slice: `font-type3-color-glyph-resource-width-currentbase`

Base accepted HEAD: `fcd1a6c4f3e872841c2f5c216a43327c2d1ba298`

## Source Truth

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level text extraction through `pdftext.extraction.dictionary_output()` and preserves text/font spans in `marker/pdf/extract_text.py`.
- PDFium Type3 loading resolves the active font encoding to glyph names, looks those names up in `/CharProcs`, and treats CharProc streams as glyph painting programs rather than page-visible text.
- PDF Type3 font behavior: `/CharProcs` keys are glyph names selected by the font encoding, and `d0`/`d1` define glyph metrics. Color operators and glyph resources paint the glyph but should not create additional extracted page text.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://pdfium.googlesource.com/pdfium/+/refs/heads/chromium/6489/core/fpdfapi/font/cpdf_type3font.cpp
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf

## Native Behavior

`PdfTextExtractor` now resolves Type3 CharProc glyph widths through named/base encodings, not only `/Encoding /Differences`. The Type3 width path now:

- builds glyph names from `/Encoding /WinAnsiEncoding`, `/StandardEncoding`, or `/MacRomanEncoding`;
- merges object-aware `/Differences` on top of the base glyph-name map;
- uses the selected glyph names to find `/CharProcs` streams and read `d0`/`d1` widths;
- keeps CharProc color operators and `/Resources /XObject` payload text out of visible WordPress paragraphs.

## Evidence

Existing Type3 focused baseline before the new slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 28 assertions, 0 failures
```

New focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses named Type3 color glyph CharProc widths while excluding resource payload text on current base

1 test files, 9 assertions, 0 failures
```

Focused font/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 642 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-color-glyph-resource-width-currentbase.php
```

The smoke emitted `WIDEBLOCK` and `thin text`, with `named_encoding_charproc_widths_resolved=true`, `thin_color_glyph_width_gap_preserved=true`, `color_glyph_resource_payload_excluded=true`, `descriptor_flags_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-type3-color-glyph-resource-width-currentbase.php
No syntax errors detected in all 3 changed PHP files.
```

## Status Delta

- Behavior tests: `848 -> 849`.
- Focused new assertions: `+9`.
- Mapped upstream/dependency semantics: `595 -> 596 / 78`.
- WordPress scenarios: `848 -> 849`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, named/simple font encoding maps, Type3 CharProc dictionary parser, stream decoder, positioned text grouping, styled-span metadata, and WordPress smoke path. Full upstream runner parity remains blocked by pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat accepted direct Type3 CharProc `d0`/`d1` width handling, Type3 descriptor `/MissingWidth`, Type3 CMap/CIDSet grouping, Type3 CharProc-to-Unicode CMap fallback, indirect Type3 Encoding Differences, Type0 CID widths, simple-font Encoding Differences, or FontDescriptor styled-span extraction. The new boundary is specifically Type3 color glyph CharProc width selection through named/base encodings while CharProc `/Resources` paint streams remain hidden from visible WordPress text.

Root harness: not run - isolated micro-slice.
