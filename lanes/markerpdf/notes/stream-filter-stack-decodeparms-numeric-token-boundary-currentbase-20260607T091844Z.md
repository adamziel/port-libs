# Stream Filter Stack DecodeParms Numeric Token Boundary Current Base

Date: 2026-06-07 UTC

Lane: markerpdf

Micro-slice: markerpdf-stream-filter-stack-boundary-current-base-20260607T091844Z

Accepted base: 8e8381377c2964e982647071fc891bbe298dcb2b

## Source Truth

PDF stream and image filter `/DecodeParms` integer operands are native PDF integer
tokens. A value such as `/Columns <integer>abc`, `/Predictor 1x`, or an indirect
integer helper body `1oops` is not a valid integer token and must not be accepted
by prefix parsing before applying Flate/LZW predictor parameters.

This stays inside the current no-GPU markerPDF scope. It uses only native
searchable-PDF stream parsing and does not run OCR, Surya, Texify, Torch,
Streamlit/FastAPI workers, external PDF tools, or model benchmarks.

## Red-First Probe

Before the fix, focused local probes imported filtered page text from malformed
numeric parameters because `decodeParmsIntegerTokenAt()` accepted the numeric
prefix:

- `/DecodeParms << /Predictor 12 /Columns <integer>abc >>` decoded the PNG-predictor
  Flate payload and leaked `Malformed Columns Prefix Leak`.
- `/DecodeParms << /Predictor 1x /Columns 1 >>` decoded the Flate payload and
  leaked `Malformed Predictor Prefix Leak`.

## Patch

- `PdfTextExtractor::decodeParmsIntegerTokenAt()` now requires the character
  after a parsed integer to be a PDF bare-token delimiter.
- The focused stream-filter stack test now covers malformed direct `/Columns`,
  malformed direct `/Predictor`, and malformed indirect `/Colors` integer helper
  operands. All malformed streams fail closed while a later valid unfiltered
  content stream remains extractable.
- The WordPress stream-filter smoke records
  `malformed_decodeparms_numeric_token_rejected=true` and
  `malformed_decodeparms_numeric_prefix_payload_excluded=true`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php`  
  Result: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`  
  Result: 1 test file, 406 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsDeclarationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDuplicateDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpDecodeParmsOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDuplicateDecodeParmsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php`  
  Result: 15 test files, 876 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php | php -r parser`  
  Result: `malformed_decodeparms_numeric_token_rejected=true`,
  `malformed_decodeparms_numeric_prefix_payload_excluded=true`,
  `executes_python_or_models=false`, `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted stream-filter stack slices for null filters,
compact DecodeParms arrays, duplicate top-level stream keys, duplicate
DecodeParms parameters, indirect multi-name filter objects, negative/malformed
`/Length`, Crypt identity/default filters, LZW EOD boundaries, CCITT/DCT image
filter metadata, trailing payload EOD rejection, or pattern filter streams.

## Dependency Closure

No new support component is needed. The existing native PDF tokenizer,
dictionary reader, indirect-reference resolver, and stream filter decoders are
reused. The remaining markerPDF dependency gap is still the intentionally
excluded GPU/model OCR/layout/equation stack under the current no-GPU direction.
