# markerPDF stream filter predictor BitsPerComponent boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T182946Z`

Accepted base: `5cc85a3f48316145610b582134be336e1d3519d4`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through the parser/pdftext boundary before OCR, layout, table, equation, or model stages. At the native PHP boundary, Flate/LZW `/DecodeParms` predictor streams should only become WordPress paragraph text when the predictor parameters are in the supported PDF component-width set.

PDF predictor DecodeParms for Flate/LZW streams use bounded component widths. This slice treats non-default predictors with unsupported `/BitsPerComponent` values as malformed DecodeParms before decoded stream bytes reach the page text parser.

## Behavior

`PdfTextExtractor::canApplyDecodeParms()` now rejects non-default predictor streams when `/BitsPerComponent` is not one of `1`, `2`, `4`, `8`, or `16`. The guard is only applied when a predictor is present and not the no-op default `1`.

The focused fixture proves:

- `/Predictor 12 /BitsPerComponent 32` fails closed before page text import.
- `/Predictor 12 /BitsPerComponent 16` still decodes and imports searchable text.
- Later unfiltered content streams on the same page remain visible.

## Red-First Evidence

Before the source edit, the focused test failed because the unsupported 32-bit predictor stream imported text:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorBitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects unsupported predictor BitsPerComponent widths before WordPress text import (lanes/markerpdf/tests/PdfParserStreamFilterPredictorBitsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Sixteen Bit Predictor Imports',
  1 => 'Visible After Predictor Bits Boundary',
)
Actual: array (
  0 => 'Unsupported Predictor Bits Leak',
  1 => 'Sixteen Bit Predictor Imports',
  2 => 'Visible After Predictor Bits Boundary',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorBitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects unsupported predictor BitsPerComponent widths before WordPress text import

1 test files, 11 assertions, 0 failures
```

Adjacent stream-filter predictor/stack checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorBitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTiffPredictorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 486 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-predictor-bits-currentbase.php
```

The smoke exits `0` and emits metadata with `unsupported_bits_rejected=true`, `valid_16_bit_predictor_preserved=true`, `visible_fallback_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-filter coverage for unsupported predictor values such as `/Predictor 16`, packed TIFF Predictor 2 samples, null filter slots, singleton DecodeParms dictionaries, compact DecodeParms alignment, stray DecodeParms, malformed integer DecodeParms tokens, duplicate stream keys, ASCII85/ASCIIHex/RunLength/LZW EOD boundaries, stale/missing `/Length`, Crypt identity filters, attachment/image/CMap filter metadata, xref repair, annotations, forms, table/equation handoffs, OCR/model behavior, or external rendering.

The bounded delta is unsupported predictor component width validation before native page text import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, DecodeParms integer parser, filter stack resolver, Flate decoder, PNG predictor decoder, text operator parser, and WordPress smoke renderer. Full scanned-PDF OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext/PDFium rendering, Surya/Torch, Texify, tabled-pdf, Streamlit/FastAPI workers, model downloads, and external OCR/rendering helpers; none were executed.

## Next Task

Continue with non-overlapping native markerPDF searchable-PDF parser behavior around font encodings/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
