# markerPDF Font CID CMap Widths Vertical Writing Current Base

Session: `port-dev-markerpdf-font56-20260602T211350Z`

Micro-slice: `font-cid-cmap-widths-vertical-writing-currentbase`

Base accepted HEAD: `0e451709894623744c6f5d4ef8d1ef3a4870fcbb`

## Source Truth

Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates digital PDF text extraction to `pdftext.extraction.dictionary_output` before Marker converts page text dictionaries into blocks and spans. The native PHP boundary for this lane is therefore text-position grouping before WordPress paragraphs are emitted, without Python/model execution.

PDF CMap/CIDFont source truth: Type0 font `/Encoding` CMaps select writing mode and map source codes to CIDs; descendant CIDFont `/DW2` and `/W2` metrics define vertical writing displacement. In vertical writing, movement along the writing direction is not a new text line by itself; line progression is orthogonal to the writing direction. This slice applies that boundary to relative `Td`/`TD` movement after the existing current-base CMap/CID width mapping.

## Native Behavior Added

`PdfTextExtractor::textLinesFromContentStream()` now branches relative text movement by writing mode:

- horizontal writing keeps the existing `Td`/`TD` behavior;
- vertical writing starts a new line on relative X movement;
- vertical writing treats relative Y movement as same-line advance or, when it lands beyond the glyph end position, a word gap.

`PdfTextExtractor::textSpanLinesFromContentStream()` now applies the same vertical line-break axis for `Td`/`TD` and `Tm` span grouping.

The focused fixture declares:

- Type0 `/Encoding` CMap stream with `/WMode 1`;
- source codes mapped to descendant CIDs 40-49 and 60-67;
- descendant CIDFont `/W2` ranges with -500 and -250 vertical displacements;
- content that uses `Td` relative moves rather than absolute `Tm` between vertical text chunks.

Before the fix, every Y movement through `Td` forced a new line, emitting `Vert`, `Import`, `Data`, and `Flow`. After the fix, the same fixture emits `VertImport` and `Data Flow`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses vertical writing mode for Td movement after CMap CID width grouping on current base
Expected: ['VertImport', 'Data Flow']
Actual: ['Vert', 'Import', 'Data', 'Flow']
1 test files, 1 assertions, 1 failures
```

Passing focused check after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses vertical writing mode for Td movement after CMap CID width grouping on current base
1 test files, 8 assertions, 0 failures
```

Adjacent font/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 657 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-cid-cmap-widths-vertical-writing-currentbase.php
```

The smoke emits Gutenberg paragraphs for `VertImport` and `Data Flow`, with `vertical_td_same_line_preserved=true`, `vertical_td_word_gap_preserved=true`, and native-only execution flags.

Syntax and JSON gates:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-cid-cmap-widths-vertical-writing-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Behavior tests: `825 -> 826`.
- Mapped upstream/dependency semantics: `579 -> 580 / 78`.
- Focused new test: `1 selected test file / 8 assertions / 0 failures`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, CMap parser, Type0 Encoding CMap CID mapping, descendant CIDFont `/W2` metric parser, existing absolute `Tm` vertical grouping model, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya OCR/layout models, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI runtime paths, benchmark tooling, OCR/PIL raster execution, and external PDF/model tooling.

## Non-Overlap

This does not repeat accepted CMap dictionary `/UseCMap` inheritance, object-valued `/UseCMap` stream bases, predefined Identity-V UseCMap codespace inheritance, direct or indirect `/W2` and `/DW2` parsing, CIDSet vertical fallback, ToUnicode surrogate/CID width grouping, indirect Type0 CMap widths, named CMap resource resolution, or absolute `Tm` vertical grouping. The new boundary is specifically relative `Td`/`TD` movement in vertical writing mode after current-base Type0 CMap CID and descendant `/W2` width selection.
