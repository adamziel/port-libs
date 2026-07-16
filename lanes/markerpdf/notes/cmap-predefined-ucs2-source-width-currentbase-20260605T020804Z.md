# markerpdf CMap predefined UCS2 source-width fallback current-base

Session: `port-dev-markerpdf-source-width-20260605T020804Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T020804Z`
Base accepted HEAD: `927c0bebf9176d6d86819fbec882fef400f8d3f6`

## Source truth

Upstream markerPDF delegates searchable-PDF text extraction to the pdftext/PDF stack before WordPress-facing Markdown conversion. The no-GPU PHP lane therefore owns the native PDF text boundary: Type0 font `/Encoding` CMaps define source character-code boundaries and writing mode, while descendant CIDFont `/W`, `/DW`, `/W2`, and `/DW2` metrics define text advance before line grouping.

This slice keeps that boundary native and bounded for predefined UCS2 CMap names such as `/UniJIS-UCS2-H`. Those names use two-byte source character codes just like the existing named encoding fallback already recognized, so the CID width path must segment `<0041>` as CID `0x0041` even when `/ToUnicode` declares one-byte rows for text decoding fallback.

## Behavior

- `PdfTextExtractor::predefinedCidCMap()` now recognizes predefined UCS2 CMap names via the existing `isPredefinedUcs2CMapName()` helper.
- Predefined UCS2 CID maps expose a two-byte codespace and preserve `-H`/`-V` writing mode.
- Focused regression: `/Encoding /UniJIS-UCS2-H` plus one-byte `/ToUnicode` rows now uses CIDFont widths for `0041..0048`, preserving the positioned `ABCD EFGH` word gap and styled span bboxes before WordPress paragraph import.

## Verification

Before this patch, the focused file had 9 PASS cases / 79 assertions. The new predefined UCS2-H case adds 1 PASS case / 10 assertions.

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 89 assertions, 0 failures
```

Adjacent CMap/font-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php
9 test files, 150 assertions, 0 failures
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
all reported no syntax errors
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `predefined_ucs2_source_width_applied=true`, `predefined_ucs2_false_join_excluded=true`, `predefined_ucs2_span_widths=true`, and the native-only execution flags.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF object parser, named CMap classifier, CMap source tokenizer, CIDFont width metrics, and text-position grouping path. GPU/model OCR, PDFium, PIL, Python model workers, and external PDF tools remain intentionally out of scope.

## Non-overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H source-width fallback, Identity-H metric-miss `/W` or `/DW` fallback, horizontal/vertical `TJ` gap handling, odd-hex padding, Type0 object-valued Encoding CMap CID width priority, UseCMap inheritance, indirect `/W`/`W2` operands, CIDSet grouping, Type3 CMap width grouping, or DCT/image/xref/parser work. The new boundary is specifically predefined UCS2-H/V CMap names feeding the CID width source-code path when `/ToUnicode` source rows are narrower.
