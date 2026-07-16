# markerPDF DCTDecode Malformed Filter Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T070903Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T070903Z`
Base accepted HEAD: `96835b31f0b7d31c68967e2c8b5127f6a9eff04e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and keeps raster image handling separate through `marker/pdf/images.py::render_image()`. Under the current no-GPU markerPDF scope, this PHP lane does not execute PDFium, PIL, OCR, Torch, Surya, Texify, or model workers.

For native WordPress import review, malformed image filter operands must fail closed. JPEG SOI/EOI framing can still own the stream boundary so fake `endstream`, `endobj`, and `obj` tokens inside DCT bytes do not become paragraph text, but unresolved filter metadata must not claim native raster decode.

## Behavior

`PdfTextExtractor` now reports `native_raster_decode=false` whenever an Image XObject, alternate image, soft mask, or explicit mask stream has unresolved/malformed filters. The existing DCT owner-boundary behavior is preserved: a malformed nested filter operand such as `/Filter [[/DCTDecode]]` still uses raw JPEG framing to keep payload text out of fallback extraction, while review metadata records `filters_resolved=false`, no decoded bytes, and no native raster decode claim.

## Red-First Evidence

After adding the focused assertion on the accepted base and before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps malformed DCTDecode filter operands review-only without native raster decode claims (lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: false
Actual: true

1 test files, 265 assertions, 1 failures
```

## Verification

Focused DCT test after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 269 assertions, 0 failures
```

Adjacent image/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1936 assertions, 0 failures
```

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-filter-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-filter-boundary-currentbase.php
```

All PHP syntax checks reported no errors. The smoke emits `malformed_filter_operand_fail_closed=true`, `stream_filters_resolved=false`, `raw_jpeg_owner_boundary_used_for_review_only_stream=true`, `xobject_native_raster_decode=false`, `xobject_decoded_with_current_filters=false`, and Python/model/PDFium/PIL/external-tool flags as false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCT SOI/EOI stream recovery, overdeclared DCT length repair, APP segment false-EOI handling, NUL-padded DCT boundaries, Flate/ASCIIHex/ASCII85 prefix DCT recovery, null filter slot handling, indirect DCT filter operands, unsupported `/Crypt` prefix boundaries, explicit Identity `/Crypt` DCT boundaries, inline DCT tokenizer boundaries, DCT CMYK/YCCK Decode review, CCITT/JPX/JBIG2 image-filter boundaries, or live OCR/model/raster rendering.

The bounded behavior is specifically malformed/unresolved DCT image filter metadata: WordPress review rows must stay fail-closed and not claim native raster decode even when raw JPEG framing is sufficient to keep image payload bytes out of visible text.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream dictionary parser, filter-name resolver, DCT/JPEG boundary checker, Image XObject review path, and WordPress smoke renderer. Full JPEG raster parity remains gated on a future native raster backend or PDFium/PIL execution, which remains out of scope for this isolated no-GPU slice.
