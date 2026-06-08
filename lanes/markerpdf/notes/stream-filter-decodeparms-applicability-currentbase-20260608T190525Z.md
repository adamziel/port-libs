# markerPDF stream-filter DecodeParms applicability boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T190525Z`

Accepted base: `038c671034a5d4c3c6fd5dda675d71a821040ce7`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser/pdftext extraction before OCR, layout, table, equation, or model handoffs. In the native PHP boundary, stream bytes become WordPress paragraphs only after the declared filter stack and filter-local DecodeParms are valid.

PDF predictor DecodeParms keys (`/Predictor`, `/Columns`, `/Colors`, and `/BitsPerComponent`) belong to Flate/LZW predictor stages. An explicitly aligned DecodeParms dictionary carrying those keys on an ASCII85 stage is not a valid no-op parameter dictionary and must fail closed before page text import.

## Behavior

`PdfTextExtractor::canApplyDecodeParms()` now rejects predictor DecodeParms keys when the active filter is not `FlateDecode`, `Fl`, `LZWDecode`, or `LZW`.

The focused fixture proves:

- `/Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms [ << /Columns N /Colors 1 /BitsPerComponent 8 >> null ]` fails closed because the predictor keys are aligned to ASCII85.
- The same ASCII85/Flate stack remains importable when `/DecodeParms [ null << /Predictor 12 /Columns N /Colors 1 /BitsPerComponent 8 >> ]` aligns those keys to Flate.
- Later unfiltered page content remains visible.

## Red-First Evidence

Before the source edit, the focused test failed because the ASCII85-aligned predictor dictionary was ignored and the tainted stream became page text:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsApplicabilityCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects predictor DecodeParms aligned to non-predictor filters before WordPress text import (lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsApplicabilityCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Flate Predictor Params Import',
  1 => 'Visible After DecodeParms Applicability',
)
Actual: array (
  0 => 'ASCII85 Predictor Params Leak',
  1 => 'Flate Predictor Params Import',
  2 => 'Visible After DecodeParms Applicability',
)

1 test files, 1 assertions, 1 failures
```

The fixture wording was then tightened to avoid the literal `DecodeParms` token in safe paragraph text while preserving the same failing boundary.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsApplicabilityCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects predictor DecodeParms aligned to non-predictor filters before WordPress text import

1 test files, 11 assertions, 0 failures
```

Adjacent stream-filter and predictor checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsApplicabilityCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterSingletonDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorRangeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterPredictorBitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTiffPredictorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 603 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-decodeparms-applicability-currentbase.php
```

The smoke exits `0` and emits `ascii85_predictor_params_rejected=true`, `flate_predictor_params_preserved=true`, `visible_fallback_preserved=true`, `filter_tokens_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-filter work for ambiguous singleton DecodeParms dictionaries, null filter slots, compact DecodeParms arrays, abbreviated filters, stale/missing `/Length`, ASCIIHex/ASCII85/RunLength/LZW EOD boundaries, duplicate stream keys, duplicate DecodeParms parameters, malformed DecodeParms integer tokens, unsupported predictor values, unsupported component widths, packed TIFF Predictor 2 samples, LZW EarlyChange, Crypt identity filters, attachment predictor extraction, image/CMap/metadata filter boundaries, xref repair, annotations/forms, table/equation handoffs, OCR/model behavior, or external rendering.

The bounded delta is explicitly aligned predictor DecodeParms keys on non-predictor stream filters.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, filter stack resolver, DecodeParms operand and integer parser, Flate/LZW predictor decoder, ASCII85 decoder, page text-token parser, and WordPress smoke renderer. Full OCR/model layout parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext/PDFium rendering, Surya/Torch, Texify, tabled-pdf, Streamlit/FastAPI workers, model downloads, and external OCR/rendering helpers; none were executed.

## Next Task

Continue with non-overlapping native markerPDF searchable-PDF parser behavior around fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
