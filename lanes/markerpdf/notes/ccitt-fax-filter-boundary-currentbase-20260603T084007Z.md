# markerPDF CCITT Fax Filter Boundary Current Base

Session: `port-dev-markerpdf-ccitt-fax-filter-20260603T084007Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260603T084007Z`
Base accepted HEAD: `72f5cb84857abafdc63cdb83c5e14ce84d9bf3fb`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible PDF text on the pdftext/PDFium text-page path and renders image crops through `marker/pdf/images.py::render_image()` / `render_bbox_image()` before emitting image spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- PDF ISO 32000-1 stream filter semantics: `/CCITTFaxDecode` is a bi-level image filter with `/DecodeParms` keys including `K`, `Columns`, `Rows`, `BlackIs1`, `EncodedByteAlign`, `EndOfLine`, `EndOfBlock`, and `DamagedRowsBeforeError`.

The native PHP no-GPU lane does not decode CCITT raster pixels. It should still preserve declared Fax parameters as review metadata and keep those raster bytes out of WordPress paragraphs.

## Native Behavior Added

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now adds `filter_details` for image XObjects. For `/CCITTFaxDecode` and `/CCF`, it records the resolved filter name, preview-only status, and CCITT DecodeParms fields from direct, indirect, and filter-array-aligned dictionaries.

CCITT image streams remain `native_raster_decode=false`, `decoded_with_current_filters=false`, and `payload_in_visible_text=false`.

## Evidence

Red-first focused failure after adding the test, before the parser helper accepted existing dictionary-body DecodeParms:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records CCITT Fax image DecodeParms without rasterizing or leaking payload text
Values are not identical
...
1 test files, 24 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text

1 test files, 40 assertions, 0 failures
```

Adjacent extractor/image-XObject gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-xobject-boundary-currentbase.php
```

The smoke emits `preview_only_filters=[["CCITTFaxDecode"],["CCF"]]`, indirect `ccitt_decode_parms.K=-1`, `ccitt_decode_parms.Columns=1728`, array-aligned `ccf_decode_parms.K=0`, `payload_in_visible_text=false`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-xobject-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-xobject-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
lane-status.json OK

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` passed with no output.

## Status Delta

- Focused assertions: `0 -> 40` in the new `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`.
- Behavior tests: `983 -> 984` expected local markerPDF PASS lines after adding one focused behavior test.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CCITT text-extraction exclusion, Separation/DeviceN CCITT preview-filter metadata in `PdfImageRenderer`, DCT/JPX/JBIG2 image-filter boundaries, inline-image DecodeParms alignment, stream-filter DecodeParms fail-closed text decoding, or generic Image XObject payload exclusion.

The new behavior is specifically parser-side Image XObject review metadata for `/CCITTFaxDecode` and `/CCF` DecodeParms on current base.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, page-resource Image XObject review path, stream filter-name resolver, DecodeParms resolver, and WordPress smoke renderer. Full CCITT raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, external PDF tool, pypdfium, or PIL execution was run.
