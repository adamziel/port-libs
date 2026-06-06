# markerPDF CMap array-decoy CID source-width current base

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T213014Z`

Base accepted HEAD: `82417ef603248e0de68523a91f6e2f08dde5f687`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This slice stays in the native no-GPU searchable-PDF path: CMap parsing, Type0 Encoding CMap CID selection, and CIDFont width grouping before WordPress text rendering. CID CMap `begincidchar` and `begincidrange` blocks are top-level source-to-CID mapping rows; array operands are not valid CID rows and should not consume declared row counts or override real top-level rows.

## Implemented Behavior

`PdfTextExtractor::parseCidChars()` and `parseCidRanges()` now use CID-specific CMap block data that blanks array operands after the existing comment, literal-string, and dictionary cleanup. This prevents malformed array-wrapped decoy rows such as `[<10> <13> 40]` or `[<10> 40]` from being counted before the real top-level CID rows.

The focused fixtures use a Type0 font whose descendant CIDFont declares wide metrics for decoy CIDs `40..43` and narrow metrics for real CIDs `60..63`. Before the fix, the array decoy consumed the declared mapping rows and WordPress text collapsed to `WideThin`. After the fix, real top-level CMap rows drive source-width grouping and text renders as `Wide Thin`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php
FAIL ignores array-wrapped CMap cidrange decoys before source-width fallback on current base
Expected: ['Wide Thin']
Actual: ['WideThin']
FAIL ignores array-wrapped CMap cidchar decoys before source-width fallback on current base
Expected: ['Wide Thin']
Actual: ['WideThin']
1 test files, 2 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php
1 test files, 22 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php
3 test files, 54 assertions, 0 failures

php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort) lanes/markerpdf/tests/PdfTextExtractorTest.php
25 test files, 1252 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-array-decoy-cid-source-width-currentbase.php
```

emits `array_decoy_cidrange_ignored=true`, `array_decoy_cidchar_ignored=true`, `source_width_spans_applied=true`, `word_gap_preserved=true`, `array_payload_excluded=true`, `wide_decoy_metrics_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss fallback, high/large CID range expansion, sparse or multi-range codespace ranking, late valid CID range/source row ordering, notdef range/char semantics, bytewise codespace membership, ToUnicode bfrange array fallback, named/predefined `usecmap`, vertical `/W2`, indirect width operands, Type3 widths, xref repair, stream filters, metadata, annotations, forms, image/filter review, OCR, or model execution.

The bounded behavior is specifically array-wrapped decoy operands inside Type0 Encoding CMap `begincidchar` and `begincidrange` blocks before declared-row slicing and CIDFont source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, CMap parser, CIDFont width metrics, text line/run/styled-span extraction, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, raster PDFium/PIL rendering, JavaScript/action execution, and exact upstream GPU/model benchmark parity remain intentionally out of scope.
