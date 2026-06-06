# markerPDF CMap Bytewise Codespace Source Width

Session: `port-dev-markerpdf-source-width-20260605T235351Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T235351Z`

Base accepted HEAD: `2faaff9b4c7feb3668f3a00ab001ec20d779e5ce`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream searchable-PDF text reaches Marker through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output(...)` / PDFium text-page boundary before OCR/model work. The native PHP fallback therefore owns low-level CMap source-code segmentation and CIDFont advance grouping when `pdftext`, PDFium, Python, OCR, and models are unavailable.
- PDF CMap code-space ranges are byte-sequence ranges. A source code such as `<3033>` is numerically between `<3030>` and `<3232>`, but it is bytewise invalid because byte `0x33` is outside the second-byte max `0x32`.

## Behavior Added

`PdfTextExtractor::toUnicodeSourceLength()` now checks code-space membership with the existing bytewise `sourceKeyMatchesCodeSpaceRange()` helper instead of a whole-integer range comparison. When a CMap declares code-space ranges but the current source prefix matches none of them, the extractor falls back to a raw byte-sized source chunk before ToUnicode decoding and glyph-width grouping.

This prevents bytewise-invalid source `<3033>` from being decoded as a synthetic UTF-16 `U+3033` glyph and from receiving the wrong multibyte CIDFont advance. The focused WordPress path now imports the text as `03A` with a bbox width of `18.0`.

## Red-First Evidence

Before the source edit, after adding the focused fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses bytewise CMap codespace membership before source-width fallback on current base
Expected: array (0 => '03A',)
Actual: array (0 => 'U+3033A',)
1 test files, 322 assertions, 1 failures
```

## Verification

Direct focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 330 assertions, 0 failures
```

Adjacent CMap/font-width gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 436 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-bytewise-codespace-source-width-currentbase.php
```

The smoke emits `bytewise_codespace_fallback_text=true`, `invalid_utf16_code_excluded=true`, `bytewise_source_widths_applied=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-cmap-bytewise-codespace-source-width-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2284 -> 2285`
- `wordpressScenarios`: `1962 -> 1963`
- Mapped upstream denominator unchanged; this is additive current-base behavior inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss ToUnicode fallback, partial metric-miss repair, horizontal/vertical `TJ` gaps, odd hex padding, one-byte codespace padding, repeated zero padding, explicit longer ToUnicode rows, malformed mixed-width ToUnicode `bfrange` rejection, predefined `usecmap`, explicit CID row recovery, zero-padded remapped CID ranges, high/large CID ranges, broad ToUnicode codespace recovery, notdef rows, ToUnicode row ordering, late narrow `bfchar` precedence, sparse code-space sequence ranking, or large ToUnicode `bfrange` lazy lookup.

The bounded behavior here is specifically bytewise CMap code-space validation before source-width fallback when a source token is whole-integer in range but byte-sequence invalid.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap parser, source-key tokenizer, CID CMap mapper, CIDFont width parser, text-positioning path, styled-span bbox builder, and WordPress smoke renderer. Full upstream runner parity remains gated by `pdftext`, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were run for this no-GPU native parser slice.
