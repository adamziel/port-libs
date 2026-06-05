# markerPDF Image XObject Decode Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T094739Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T094739Z`
Base accepted HEAD: `54d4990abd113041d05e6000e22de0cf52a8be6c`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable text extraction from image raster handoff: `marker/pdf/extract_text.py` gets text pages through PDFium/pdftext, while `marker/pdf/images.py::render_image()` and `render_bbox_image()` hand page regions to an RGB image path.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

This native PHP slice stays on the no-GPU parser boundary. It does not rasterize images; it exposes the already parsed PDF `/Decode` metadata on Image XObject review rows so WordPress import can audit the Decode-to-RGB handoff without leaking image stream text.

## Native Behavior Added

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now includes parser-side Image XObject Decode metadata on each image review entry:

- `image_decode` with component ranges, expected component count, identity/inverted component review, and explicit/default source;
- `image_decode_applied_before_rgb` when the Decode array is valid for the resolved component count;
- `image_decode_component_mismatch` when an explicit Decode array is present but its component count cannot be applied.

The focused fixture covers a valid `/DeviceRGB /Decode [1 0 0 1 0 1]` review, a fail-closed `/DeviceCMYK /Decode [0 1 1 0]` mismatch, and an `/ImageMask true` image with default `[0 1]` Decode metadata. All image payload bytes remain excluded from visible text and JSON review output.

## Evidence

Pre-change focused baseline:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 634 assertions, 0 failures
```

Red-first focused failure after adding the test, before exposing `image_decode` from the review row:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
PHP Warning:  Undefined array key "image_decode" in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php on line 858
FAIL exposes image XObject Decode arrays before RGB preview review
1 test files, 637 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 658 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-decode-boundary-currentbase.php
```

The smoke emits `image_xobject_count=3`, `invoked_image_xobject_count=3`, `rgb_decode_applied_before_rgb=true`, `rgb_decode_inverted_components=[0]`, `cmyk_decode_component_mismatch=true`, `cmyk_decode_applied_before_rgb=false`, `stencil_default_decode_source=default`, `payload_excluded_from_text=true`, and the two expected paragraph blocks.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject resource expansion, optional-content hiding, page clipping, page rotation/UserUnit placement, exact-generation auxiliary image metadata, SMask/Mask metadata, ColorKey masks, named ColorSpace review, ExtGState review, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline image Decode previews, or renderer-level base image `/Decode` sample mapping. It only exposes Image XObject `/Decode` review metadata that `PdfTextExtractor` already computed but did not return on the top-level image review rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, stream decoder, image review rows, and existing Decode review helper. Full raster parity remains gated on pypdfium/PIL/PDFium or a future native raster backend; this slice does not execute Python, models, OCR, external PDF tools, pypdfium, or PIL.
