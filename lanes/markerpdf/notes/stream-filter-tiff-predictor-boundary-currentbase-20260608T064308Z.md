# markerPDF stream filter TIFF predictor packed-sample boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260608T064308Z`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF stream decoding to pdftext/PDFium before OCR/layout/model stages.
- PDF Flate/LZW `/DecodeParms` Predictor 2 is the TIFF horizontal predictor. It reconstructs each color component sample from the previous sample of the same color. For packed 1-, 2-, and 4-bit rows this must happen at sample granularity, not by adding previous decoded bytes.

## Behavior

This slice updates native searchable-PDF stream decoding for Flate streams with:

```pdf
/DecodeParms << /Predictor 2 /Colors 1 /BitsPerComponent 4 /Columns N >>
```

The stream decoder now unpacks each TIFF predictor row into component samples, restores deltas per color component, repacks the row, and then passes the recovered content stream to the text operator parser. The existing byte-aligned 8-bit path remains intact for prior Predictor 2 coverage.

## Evidence

Red-first focused run on accepted base `2963610daf96767276a1776d5d1df7e0ba0844de`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterTiffPredictorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL applies packed TIFF predictor samples before WordPress paragraph rendering
Values are not identical
Expected: 'TIFF Predictor Four Bit Visible
Packed Nibbles Preserved
Visible After Packed TIFF Boundary'
Actual: 'Visible After Packed TIFF Boundary'

1 test files, 1 assertions, 1 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterTiffPredictorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS applies packed TIFF predictor samples before WordPress paragraph rendering

1 test files, 10 assertions, 0 failures
```

Adjacent stream/text coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 1066 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-tiff-predictor-boundary-currentbase.php --self-test
```

The smoke exits `0` with `packed_tiff_predictor_decoded=true`, `dictionary_tokens_not_leaked=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted null DecodeParms alignment, indirect DecodeParms value consumption, malformed DecodeParms integer tokens, duplicate DecodeParms parameters, PNG predictor rows, byte-oriented TIFF predictor handling, LZW EarlyChange boundaries, ASCII85/Flate stack endstream recovery, Crypt identity filters, or xref repair behavior.

The new behavior is specifically the packed sub-byte sample boundary for native TIFF Predictor 2 restoration before searchable page text import.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object scanner, stream dictionary reader, Flate decoder, DecodeParms parser, predictor decoder, content-token parser, and WordPress smoke path. Full OCR/model layout parity remains intentionally out of scope under the no-GPU markerPDF direction.
