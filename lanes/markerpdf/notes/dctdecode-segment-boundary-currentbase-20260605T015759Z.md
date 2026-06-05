# markerPDF DCTDecode APP Segment Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T015759Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T015759Z`
Base accepted HEAD: `a5ec9ff86bd1a52891911ed457b520a215e6d13b`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction from raster image rendering: `marker/pdf/extract_text.py::get_text_blocks()` delegates text to `pdftext.dictionary_output()`, while `marker/pdf/images.py::render_image()` renders through PDFium/PIL and converts images to RGB.
- Under the current no-GPU/no-external-renderer markerPDF scope, DCTDecode JPEG bytes remain review-only raster payloads. The native parser still owns the stream boundary that prevents JPEG payload bytes and fake PDF object tokens from becoming WordPress paragraphs.
- JPEG APP/COM-style markers are length-coded. A raw `FF D9` byte pair inside the segment payload is data, not the JPEG EOI marker.

## Behavior

`PdfTextExtractor` now uses a segment-aware DCTDecode EOI scan before accepting an `endstream` terminator. It skips valid length-coded JPEG segments, including APP payload bytes that contain fake `FF D9`, `endstream`, and fake object text, and accepts the terminator only after the actual EOI. Existing lightweight malformed JPEG fixtures still use the lenient fallback path so accepted boundary tests remain stable.

This prevents stale `/Length` values from truncating DCT image streams at fake EOI bytes inside APP segment data. Page Image XObject review rows now report the recovered raw JPEG stream length through the actual EOI, with `decoded_with_current_filters=false` and DCTDecode still preview-only.

## Red-First Evidence

Before the source change, a one-off focused fixture leaked fake text from a valid APP segment:

```text
array (
  0 => 'Before APP DCT stream',
  1 => 'APP Segment DCT Payload Leak',
  2 => 'After APP DCT stream',
)
```

The new focused test captures the same boundary and passes after the patch.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-segment-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps DCTDecode APP segment EOI decoys inside JPEG payload boundaries

1 test files, 20 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1245 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-segment-boundary-currentbase.php
```

The smoke emitted `fake_eoi_inside_app_segment_ignored=true`, `stale_length_recovered_to_actual_eoi=true`, `dctdecode_image_payload_excluded_from_text=true`, `xobject_preview_only_filters=["DCTDecode"]`, `xobject_native_raster_decode=false`, `xobject_decoded_with_current_filters=false`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted generic DCTDecode review-only filter metadata, DCT `/DecodeParms /ColorTransform`, DCT CMYK/YCCK Adobe transform planning, DCT `/Decode` sample review, inline DCT JPEG EOI tokenization, direct stale-Length DCT stream terminator recovery, NUL-padded DCT boundaries, Flate/ASCII85 prefix DCT boundaries, CCITT/JPX/JBIG2 image-filter boundaries, broad stream filter-stack recovery, or live OCR/model/raster rendering.

The bounded behavior is specifically fake EOI bytes inside valid length-coded DCTDecode JPEG segment payloads.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF stream parser, existing DCTDecode preview-only filter boundary, stream-length recovery, Image XObject review, and WordPress smoke path. Full JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
