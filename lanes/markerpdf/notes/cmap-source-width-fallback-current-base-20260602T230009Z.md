# markerPDF CMap Source Width Fallback Current Base

Session: `port-dev-markerpdf-source-width-20260602T230009Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260602T230009Z`

Base accepted HEAD: `1c11c94b45001e6d7041475e1155fe1067d73191`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker converts dictionaries into spans, lines, blocks, and Markdown.
- The native PHP fallback therefore has to preserve PDF text source-code boundaries and CIDFont `/W` advances before WordPress paragraph grouping when Python, pdftext, pypdfium2/PDFium, and model workers are unavailable.
- Relevant dependency behavior is CMap/font-width source segmentation: ToUnicode CMaps decode source character codes, while CIDFont widths are keyed by source CIDs. The width path must not count zero padding bytes as separate glyphs when a minimal ToUnicode CMap omits `begincodespacerange`.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://github.com/datalab-to/pdftext
- https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_cmap.py

## Behavior Added

`PdfTextExtractor::textOperandSourceKeysForFontWidths()` now has a bounded zero-padded source-key fallback for glyph advance only. When a ToUnicode or CID width map has no code-space ranges, a source operand shaped like `<0041>` can be treated as one source CID `0x0041` for width lookup if:

- the leading chunk is zero padding;
- the suffix is an existing mapped source key;
- the combined source CID has explicit width or CIDSet evidence.

Visible ToUnicode decoding is unchanged; this only corrects glyph count and advance selection before same-line `Tm` gap decisions and native styled-span bbox widths.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` builds a Type0 `/Identity-H` font with:

- a minimal ToUnicode CMap that maps low-byte source keys `<41>` through `<48>` and omits `begincodespacerange`;
- zero-padded two-byte source operands `<0041004200430044>` and `<0045004600470048>`;
- descendant CIDFont `/W [65 68 1000 69 72 250]`;
- a second `Tm` positioned exactly far enough to require a word gap only if the first operand advances as four 1000-unit CIDs.

Before the fix, the width path counted `00` bytes as separate 500-unit fallback glyphs, over-advanced `ABCD`, and emitted `ABCDEFGH`. After the fix, source widths are `0041` through `0048`, so the extractor emits `ABCD EFGH` and styled span bboxes `[0,0,48,12]` plus `[48,0,60,12]`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses zero-padded CMap source widths before CID fallback text gaps on current base
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => 'ABCDEFGH',)
1 test files, 1 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
1 test files, 10 assertions, 0 failures
```

Adjacent regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 646 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-zero-padded-source-width-currentbase.php
```

The smoke emits `ABCD EFGH`, `positioned_word_gap_preserved=true`, `zero_padded_source_widths_applied=true`, `narrow_second_span_width_applied=true`, `raw_nul_bytes_excluded=true`, and native-only execution flags.

## Status Delta

- `phpPass`: `945 -> 946`
- `wordpressScenarios`: `945 -> 946`
- Mapped upstream denominator stays `664 / 78`; this is a focused current-base PHP behavior slice within the already mapped CMap/font-width source surface.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted basic mapped source-width fallback, ToUnicode code-space fallback, Identity-H/V no-ToUnicode fallback, Type0 Encoding CMap CID width priority, indirect `/W` or `/DW` parsing, Type3 CMap/CIDSet width grouping, named Type0 CMap resource spacing, source-space word spacing, vertical UCS2 CMap spacing, or styled-span CID resource width slice. The new boundary is specifically zero-padded source-code width segmentation when a minimal ToUnicode CMap omits `begincodespacerange` and explicit CID widths prove the combined source CID.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, CIDFont width parser, content-token text-positioning path, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
