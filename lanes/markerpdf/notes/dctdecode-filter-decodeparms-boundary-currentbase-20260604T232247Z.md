# markerPDF DCTDecode DecodeParms Filter Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260604T231623Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260604T231623Z`
Base accepted HEAD: `fd0f5327abfd3b58715219a1c13c4c8295941253`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable text extraction from image rendering:

- `marker/pdf/extract_text.py::get_text_blocks()` delegates text extraction to `pdftext.extraction.dictionary_output(...)`.
- `marker/pdf/images.py::render_image()` renders pages/crops through PDFium and converts output to RGB.

The native PHP lane does not execute PDFium, PIL, OCR, Torch, or model workers. DCTDecode JPEG streams therefore remain review-only raster payloads, but their PDF parser-side `/DecodeParms /ColorTransform` policy is still important metadata for future CMYK/YCCK RGB preview decisions and WordPress media review.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now exposes DCTDecode filter `/DecodeParms /ColorTransform` in `image_filter_details[*].decode_parms` with:

- `type=DCTDecode`;
- `color_transform`;
- `valid_color_transform`.

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now exposes the same fields for page Image XObject review rows, including indirect DecodeParms dictionaries aligned to filter arrays. This does not add JPEG raster decoding and does not promote JPEG payload bytes into visible text.

## Red-First Evidence

Before the source change, after adding the focused assertions:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL marks DCTDecode image filters review-only before RGB preview metadata
FAIL records DCTDecode ColorTransform DecodeParms on image XObject review rows

1 test files, 17 assertions, 2 failures
```

Both failures were missing `color_transform` and `valid_color_transform` fields while the existing DCT payload text exclusion stayed intact.

## Verification

Focused DCT test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 20 assertions, 0 failures
```

Focused DCT/image/text family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1361 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-import.php
```

The smoke emitted `dct_decodeparms_color_transform=1`, `xobject_dct_decodeparms_color_transform=0`, `xobject_preview_only_filters=["DCTDecode"]`, `excluded_dctdecode_image_noise=true`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCTDecode JPEG stream text exclusion, inline DCT JPEG EOI delimiter validation, DCT CMYK/YCCK Adobe APP14 transform planning, DCT `/Decode` sample review, DCT preview-only filter classification, JPX/JBIG2/CCITT image-filter text exclusion, Image XObject payload exclusion, or stream filter-stack recovery.

The new boundary is specifically DCTDecode `/DecodeParms /ColorTransform` metadata on generic filter review and page Image XObject review rows.

## Dependency Closure

No new support component is needed. This slice reuses native PDF dictionary parsing, filter-array DecodeParms alignment, indirect object resolution, Image XObject review, and the WordPress smoke path. Full JPEG raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; this patch does not execute Python, OCR, models, PDFium, PIL, Poppler, Ghostscript, or other external PDF tools.
