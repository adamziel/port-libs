# markerPDF Font Encoding Differences CMap Width Review Current Base

Session: `port-dev-markerpdf-font33pdf-20260602T171314Z`

Micro-slice: `font-encoding-differences-cmap-width-review-currentbase-20260602T171314Z`

Base accepted HEAD: `49180e79432b8b918699ff28f84476d5fe362bc7`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates low-level PDF page text extraction to `pdftext.extraction.dictionary_output` before Marker block assembly. The native PHP fallback therefore has to preserve PDF parser font decoding and glyph advance decisions before WordPress paragraph grouping.

The local markerPDF upstream clone recorded by the manifest is not present in the current shared upstream cache, so this slice uses the pinned manifest plus accepted markerPDF font-parser notes as source truth. The relevant PDF parser boundary is object-aware Type3 font parsing: `/CharProcs` widths are selected by glyph names from the active `/Encoding /Differences`, while `/ToUnicode` CMaps supply decoded Unicode text. When `/Encoding` and `/Differences` are indirect, width lookup still must resolve those objects before text-gap grouping.

## Native Behavior

`PdfTextExtractor::type3CharProcWidths()` now asks `encodingDifferencesGlyphNames()` to resolve indirect `/Encoding` dictionaries and indirect `/Differences` arrays before matching glyph names to `/CharProcs`.

The focused PDF uses:

- a Type3 font with `/Encoding 19 0 R`;
- object `19`, an encoding dictionary with `/Differences 22 0 R`;
- object `22`, glyph names for wide `WideBlock` codes and thin `Thin Text` codes;
- a `/ToUnicode` CMap for visible text decoding;
- Type3 `/CharProcs` whose `d0` and `d1` widths decide whether positioned text fragments join or separate.

Before the fix the extractor decoded text through ToUnicode but used fallback 500-unit widths, producing `Wide Block` and `ThinText`. After the fix it emits `WideBlock` and `Thin Text`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect Type3 Encoding Differences before ToUnicode CMap width grouping on current base (lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'WideBlock',
  1 => 'Thin Text',
)
Actual: array (
  0 => 'Wide Block',
  1 => 'ThinText',
)

1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect Type3 Encoding Differences before ToUnicode CMap width grouping on current base

1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 687 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-encoding-differences-cmap-width-review-currentbase.php
```

The smoke emits Gutenberg paragraphs for `WideBlock` and `Thin Text` with `to_unicode_cmap_text_decoded=true`, `indirect_type3_encoding_differences_resolved=true`, `charproc_width_gap_preserved=true`, `raw_source_codes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Behavior tests: `583 -> 584`.
- Focused new test: `8` assertions.
- Mapped upstream/dependency semantics: `420 -> 421 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, object-aware array resolution, ToUnicode CMap parsing, Type3 CharProc width parser, text-position grouping path, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, and external PDF/model execution.

## Non-Overlap

This does not repeat accepted simple-font direct or indirect Encoding Differences, indirect simple-font `/FirstChar` or `/Widths`, Base14 widths, direct Type3 CharProc widths, Type0 `/Encoding` CMap CID width priority, named Type0 CMap resources, ToUnicode surrogate/CID width row counts, CIDFont `/W`/`/DW`/`/W2` metrics, CIDSet grouping, or page font resource scoping. The new boundary is specifically Type3 CharProc width selection when `/Encoding` and `/Differences` are both indirect while ToUnicode CMap decoding supplies visible text.
