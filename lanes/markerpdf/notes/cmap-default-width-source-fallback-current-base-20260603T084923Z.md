# markerPDF CMap Default Width Source Fallback Current Base

Session: `port-dev-markerpdf-source-width-20260603T084923Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260603T084923Z`

Base accepted HEAD: `f0bd4183a2ffe1c741d3688a1bfed43e7facac09`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker converts dictionaries into spans, lines, blocks, and Markdown.
- The native PHP fallback must preserve PDF source-code glyph boundaries and CIDFont width advances before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, and model workers are unavailable.
- This slice follows the same dependency boundary as the accepted zero-padded source-width fallback, but extends it to CIDFonts whose only width evidence is `/DW`. `/DW` is the default width for CIDs not listed in `/W`, so it is valid width evidence for collapsing a zero-padded source operand such as `<0041>` into one CID instead of counting the leading `00` byte as a separate glyph.

## Behavior Added

`PdfTextExtractor::fontWidthMapContainsCid()` now treats a resolved `cidDefaultWidth` as valid CID width evidence when the zero-padded source-key fallback decides whether a CMap source code can be collapsed for glyph advance.

The change is bounded to width-key segmentation. Visible ToUnicode decoding is unchanged.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` now includes a Type0 `/Identity-H` font with:

- a minimal ToUnicode CMap that maps low-byte source keys `<41>` through `<48>` and omits `begincodespacerange`;
- zero-padded two-byte source operands `<0041004200430044>` and `<0045004600470048>`;
- descendant CIDFont `/DW 1000` and no `/W` or `/CIDSet`;
- a second `Tm` positioned to require a WordPress paragraph word gap only if the first operand advances as four 1000-unit CIDs.

Before the source repair, the extractor counted padding bytes as separate default-width glyphs, over-advanced `ABCD`, and emitted `ABCDEFGH`. After the repair, the same fixture emits `ABCD EFGH` and styled span bboxes `[0,0,48,12]` plus `[48,0,96,12]`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
FAIL uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => 'ABCDEFGH',)
1 test files, 11 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
1 test files, 20 assertions, 0 failures
```

Adjacent regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 677 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `ABCD EFGH`, `default_width_source_fallback_applied=true`, `padding_bytes_not_counted_as_glyphs=true`, and native-only execution flags.

## Status Delta

- `phpPass`: `992 -> 993`
- `wordpressScenarios`: `992 -> 993`
- Mapped upstream denominator stays `684 / 78`; this is a focused current-base PHP behavior slice within the already mapped CMap/font-width source surface.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted explicit `/W` zero-padded source-width fallback, ToUnicode code-space fallback, Identity-H/V no-ToUnicode fallback, direct or indirect `/DW` parsing when CMap code-space ranges are present, CIDSet default-width grouping, simple-font width resolution, Type3 CharProc width handling, vertical `/W2`/`DW2`, source-space word spacing, or FontDescriptor review. The new boundary is specifically zero-padded source-code width segmentation when a minimal ToUnicode CMap omits `begincodespacerange` and a descendant CIDFont uses `/DW` as the only width source.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, CIDFont default-width parser, content-token text-positioning path, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
