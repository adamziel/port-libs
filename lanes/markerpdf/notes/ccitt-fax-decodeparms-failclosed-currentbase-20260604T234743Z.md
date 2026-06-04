# markerPDF CCITT Fax DecodeParms Fail-Closed Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260604T234743Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260604T234743Z`
Base accepted HEAD: `d74bad6c88fb561dfd80595abb30cd894a59e542`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible searchable PDF text on the pdftext/PDFium text-page path and routes image pixels through `marker/pdf/images.py::render_image()` / `render_bbox_image()` before RGB image handoff.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://tcpdf.org/docs/srcdoc/tc-lib-pdf-filter/classes-Com-Tecnick-Pdf-Filter-Type-CcittFax/

The native PHP no-GPU lane does not decode CCITT raster pixels. It should still distinguish declared invalid fax `/DecodeParms` operands from absent/default operands in review metadata, while keeping raster payload bytes out of WordPress paragraphs. `/Rows 0` remains valid/unknown per CCITT parameter defaults; negative rows and unresolved or non-integer/boolean operands are review-invalid.

## Native Behavior Added

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now marks malformed or unresolved `/CCITTFaxDecode` and `/CCF` `/DecodeParms` operands with:

- `valid_decode_parms=false`
- `invalid_decode_parms_fields=[...]`
- `decode_parms_review=invalid_ccitt_decodeparms_fail_closed`

Valid CCITT parameter rows keep the existing compact shape, so prior review output for ordinary direct, indirect, and array-aligned `/DecodeParms` remains stable.

CCITT image streams remain `native_raster_decode=false`, `decoded_with_current_filters=false`, and `payload_in_visible_text=false`.

## Evidence

Red-first focused failure after adding the invalid-parameter regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
FAIL marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
...
1 test files, 50 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults

1 test files, 63 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-failclosed-currentbase.php
```

The smoke emits `ccitt_valid_decode_parms=false`, `ccitt_invalid_fields=["k","columns","rows","black_is_1","damaged_rows_before_error"]`, `ccf_valid_decode_parms=false`, `ccf_invalid_fields=["columns","rows","end_of_line"]`, `payload_in_visible_text=false`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Final focused/adjacent gate on the completed patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 966 assertions, 0 failures
```

Syntax/JSON/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-failclosed-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-failclosed-currentbase.php

php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK
lanes/markerpdf/lane-status.json OK

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` passed with no output.

## Status Delta

- Focused PASS cases: `1127 -> 1128` expected markerPDF lane status.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `40 -> 63`.
- WordPress scenarios: `1120 -> 1121` expected lane status.
- Mapped current-base behavior: add `pdfCcittFaxDecodeParmsFailClosedCurrentBase`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT text-extraction exclusion, CCITT Image XObject filter metadata for valid direct/indirect/array-aligned parameters, Separation/DeviceN CCITT preview-filter metadata in `PdfImageRenderer`, inline CCITT tokenizer recovery, DCT/JPX/JBIG2 preview-only image-filter boundaries, inline-image DecodeParms alignment, stream-filter DecodeParms fail-closed text decoding, or generic Image XObject payload exclusion.

The new bounded behavior is specifically invalid CCITT Fax `/DecodeParms` review metadata on the current Image XObject parser boundary.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, page-resource Image XObject review path, stream filter-name resolver, DecodeParms parser, and WordPress smoke renderer. Full CCITT raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, external PDF tool, pypdfium, or PIL execution was run.
