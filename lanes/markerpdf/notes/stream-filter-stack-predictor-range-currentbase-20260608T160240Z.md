# markerPDF Stream Filter Predictor Range Boundary

## Scope

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T160240Z`

Accepted base: `450f9d85c694c1f05e86446b6abdb8ba44f420f1`

This patch keeps the no-GPU markerPDF lane focused on native PDF parser behavior. It tightens `/DecodeParms /Predictor` validation for text/CMap stream filters:

- `/Predictor 1` remains the no-op default.
- Flate/LZW predictor streams accept TIFF predictor `2` and PNG predictors `10..15`.
- Unsupported predictor values such as `16` now fail closed as malformed DecodeParms before Flate/LZW decoding is attempted.

## Red-First Evidence

Before the source edit, this focused command failed:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php`

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL classifies unsupported Flate predictor values as malformed DecodeParms before CMap decoding (lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0
PASS rejects unsupported predictor values in stacked text streams before WordPress text import

1 test files, 15 assertions, 1 failures
```

The current base already prevented visible text leakage for the bad stacked stream, but CMap filter review classified `/Predictor 16` as `reject_filter_decode_errors` with `invalid_decodeparms_parameter_count=0`. The desired boundary is `filter_decode_not_reached` with `reject_malformed_decodeparms_parameters`.

## Implementation

`PdfTextExtractor::canApplyDecodeParms()` now delegates predictor-range checks to `decodeParmsPredictorValueIsSupportedForFilter()`. The helper preserves the existing default/no-op behavior for predictor `1`, keeps Flate/LZW predictor support bounded to `2` and `10..15`, and rejects non-default predictors on non-Flate/LZW text filters.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS classifies unsupported Flate predictor values as malformed DecodeParms before CMap decoding
PASS rejects unsupported predictor values in stacked text streams before WordPress text import

1 test files, 28 assertions, 0 failures
```

Adjacent stream-filter/DecodeParms/CMap family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 2134 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-predictor-range-currentbase.php
```

The smoke exits `0` and emits metadata with `unsupported_predictor_rejected=true`, `visible_fallback_preserved=true`, `invalid_decodeparms_parameter_count=1`, `filter_decode_error_count=0`, `filter_decode_policy=filter_decode_not_reached`, `decodeparms_operand_policy=reject_malformed_decodeparms_parameters`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-filter stack coverage for null filter slots, compact DecodeParms arrays, abbreviated filters, stale/missing `/Length`, ASCIIHex/ASCII85/RunLength/LZW EOD boundaries, duplicate top-level stream keys, duplicate DecodeParms parameters, malformed numeric DecodeParms tokens, attachment predictor extraction, CMap malformed filter operands, image DCT/CCITT metadata, xref/object-stream filters, page-resource inheritance, OCR/model behavior, or external rendering. The bounded delta is predictor value range classification before native stream decoding.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, filter stack resolver, DecodeParms operand alignment and integer parser, Flate/LZW decoders, CMap review path, page text importer, and WordPress smoke renderer. Full scanned-PDF OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext/PDFium rendering, Surya/Torch, Texify, tabled-pdf, Streamlit/FastAPI workers, model downloads, and external OCR/rendering helpers; none were executed.

## Next

Continue with non-overlapping native markerPDF parser behavior around searchable-PDF text extraction, fonts/CMaps, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, and supplied-boundary table/equation handoffs.
