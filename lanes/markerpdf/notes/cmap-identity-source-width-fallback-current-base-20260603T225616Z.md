# markerPDF CMap Identity Source Width Fallback Current Base

Session: `port-dev-markerpdf-source-width-20260603T225616Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260603T225616Z`

Base accepted HEAD: `5b96b09569574c4c3f26d7d99da635ab4d7632c0`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker converts dictionaries into spans, lines, blocks, and Markdown.
- The native no-GPU PHP fallback therefore has to preserve PDF source-code glyph boundaries and CIDFont width advances before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, and model workers are unavailable.
- PDF Type0 predefined `/Identity-H` CMap source codes are two-byte CIDs. Even if a narrow ToUnicode CMap declares one-byte source ranges, descendant CIDFont `/W` and `/DW` advances are keyed by the Type0 Encoding CMap CIDs, not by each padding byte.

## Behavior Added

`PdfTextExtractor::cidEncodingMapFromNamedCMap()` now falls back to the built-in predefined CID CMap metadata for direct named `/Identity-H` and `/Identity-V` encodings when no embedded named CMap stream exists.

That gives the width path `cidCodeSpaceRanges` for direct predefined Type0 encodings, so CMap width segmentation uses two-byte source codes before CIDFont `/W` lookup. Visible ToUnicode decoding is unchanged.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` now includes a Type0 font with:

- direct `/Encoding /Identity-H`;
- a ToUnicode CMap that deliberately declares one-byte `<00> <FF>` source ranges and maps only low-byte keys `<41>` through `<48>`;
- two-byte text operands `<0041004200430044>` and `<0045004600470048>`;
- descendant CIDFont `/W [65 68 1000 69 72 250]`.

Before the source repair, styled-span width lookup followed the one-byte ToUnicode source range, counted each `00` byte as a default glyph, over-advanced `ABCD`, and emitted `ABCDEFGH` without the WordPress paragraph gap. After the repair, `/Identity-H` supplies the source width for CIDFont metrics, so the extractor emits `ABCD EFGH` and styled span bboxes `[0,0,48,12]` plus `[48,0,60,12]`.

## Evidence

Red-first probe before the source fix:

```text
array (
  0 =>
  array (
    0 => 'ABCDEFGH',
  ),
  1 =>
  array (
    0 => 'ABCD',
    1 => 'EFGH',
  ),
  2 =>
  array (
    0 =>
    array (
      'text' => 'ABCD',
      'bbox' =>
      array (
        0 => 0.0,
        1 => 0.0,
        2 => 96.0,
        3 => 12.0,
      ),
    ),
  ),
)
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps

1 test files, 30 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `ABCD EFGH`, `predefined_identity_source_width_applied=true`, `padding_bytes_not_counted_as_glyphs=true`, and native-only execution flags.

## Status Delta

- `phpPass`: `1030 -> 1031`
- `wordpressScenarios`: `1030 -> 1031`
- Mapped upstream denominator stays unchanged; this is a focused current-base PHP behavior slice within the already mapped CMap/font-width source surface.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted no-codespace zero-padded source-width fallback, `/DW`-only source-width fallback, ToUnicode code-space fallback, no-ToUnicode Identity-H/V fallback, indirect `/Encoding` name resolution, object-valued Type0 Encoding CMaps, `/UseCMap` inheritance, indirect `/W` or `/DW` parsing, CIDSet default-width grouping, Type3 CharProc width handling, vertical `/W2`/`DW2`, source-space word spacing, or styled-span vertical bbox advance. The new boundary is specifically direct predefined `/Identity-H` source-code width metadata overriding a too-narrow ToUnicode codespace for CIDFont advance lookup.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, predefined CID CMap metadata, CIDFont width parser, content-token text-positioning path, styled-span extraction path, and WordPress smoke renderer. Full upstream runner parity remains gated on pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers; none were run for this bounded PHP slice.
