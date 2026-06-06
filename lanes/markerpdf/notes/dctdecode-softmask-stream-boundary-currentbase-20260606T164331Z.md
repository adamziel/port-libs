# DCTDecode Soft-Mask Stream Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T164331Z`
Base: `beefb9c61faa06047be0268dd66c6d5afebefc6c`

## Source Truth

Upstream markerPDF keeps PDF image streams on the image/review path instead of promoting raster payload bytes to document text. For the no-GPU PHP port, DCTDecode/JPEG stream handling uses native JPEG marker framing as the boundary source: SOI/SOS/EOI marker offsets identify the review stream, and fake PDF tokens inside JPEG scan payloads must not become WordPress paragraphs.

This slice extends the already accepted primary image and explicit-mask DCT boundary behavior to nested `/SMask` image streams.

## Implementation

`PdfTextExtractor::imageXObjectSoftMaskStreamReview()` now records `dctdecode_stream_boundary` for nested soft-mask image streams by reusing `dctPreviewStreamBoundaryReviewForFilters()`. The review row carries source, SOI/EOI offsets, raw/review lengths, marker-framing flags, payload leak status, and native-raster execution status while keeping the stream review-only.

The WordPress smoke for DCTDecode nested masks now asserts that the soft-mask boundary source is `dctdecode_jpeg_marker_boundary`, marker framing was used, and payload bytes remain excluded from visible text.

## Evidence

Red-first before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSoftMaskStreamBoundaryCurrentBaseTest.php
=> 1 test files, 18 assertions, 1 failures
Failure: Nested DCT soft-mask stream boundary should be present.
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSoftMaskStreamBoundaryCurrentBaseTest.php
=> 1 test files, 34 assertions, 0 failures
```

DCT family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSoftMaskStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php
=> 5 test files, 848 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-mask-boundary-currentbase.php
=> reports soft_mask_dctdecode_stream_boundary=dctdecode_jpeg_marker_boundary, soft_mask_dctdecode_marker_framing_used=true, soft_mask_payload_in_visible_text=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted DCTDecode text exclusion, CMYK Adobe-transform preview planning, DecodeParms/filter-chain behavior, inline-image DCT boundaries, segment/SOS/renderer stream boundaries, explicit mask clipping, post-EOI clipping, JPX/JBIG2/CCITT boundaries, or runtime/import preflight work. It only fills the missing nested soft-mask review metadata path.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, stream payload recovery, DCT/JPEG marker scanner, image XObject review metadata, and existing WordPress smoke path. It does not execute Python, pypdfium, PIL, Surya/Texify/Torch, OCR/model workers, or external PDF tools.
