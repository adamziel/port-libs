# CCITT Fax DecodeParms generation boundary current-base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T181228Z`  
Accepted base: `830c9a682bd827bfdd2817a678f3fc18d9745b5a`

## Source truth

Upstream markerPDF hands PDF image raster handling to native PDF dependencies and model-adjacent pipelines; under the current no-GPU lane scope this port keeps CCITT image filters review-only while preserving searchable text boundaries. PDF indirect references are object-and-generation qualified, so image review metadata must not let stale generation-zero helper objects override current-generation `/DecodeParms` operands.

## Patch

`PdfImageRenderer::booleanFromPdfValue()` now uses the existing generation-aware indirect-reference token and object-map lookup used by the generic value resolver. This keeps `/BlackIs1`, `/EncodedByteAlign`, `/EndOfLine`, and `/EndOfBlock` aligned with exact nonzero-generation `/DecodeParms` dictionaries before CCITT coding, row-end ownership, and ImageMask polarity review.

Non-overlap: this does not change the already accepted indirect `/Rows` height fallback, chained generation-zero DecodeParms resolution, CCITT filter array tail handling, row EOL/EOFB ownership, native-prefix filter stacks, or CCITT array/dictionary operand fail-closed behavior.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsGenerationBoundaryCurrentBaseTest.php
=> 1 test files, 4 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsGenerationBoundaryCurrentBaseTest.php
=> 1 test files, 18 assertions, 0 failures

php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -name 'PdfCcittFax*Test.php' -print | sort)
=> 6 test files, 1363 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-generation-currentbase.php
=> exits 0; metadata reports decodeparms_generation_exact=true, stale_generation_zero_ignored=true, native_raster_decode=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. The patch reuses the existing bounded native PHP PDF object-map helpers in `PdfImageRenderer` and does not invoke pypdfium, PIL, OCR, Torch, Surya, Texify, external PDF tools, or live services.

## Next

Good follow-up targets are parser-level object-generation coverage for extracted Image XObject dictionaries, or another distinct CCITT/filter-stack boundary. Do not repeat generation-zero boolean recursion or indirect `/Rows` fallback.
