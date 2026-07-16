# markerPDF DCTDecode SOS Marker Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T192315Z`

## Source Truth

Upstream markerPDF separates searchable text extraction from image rendering: text is extracted before image payloads route through the image-rendering path. Under the current no-GPU lane scope, DCT/JPEG bytes remain review-only raster payloads, but the native parser still owns the PDF stream boundary that prevents JPEG scan data from becoming WordPress paragraphs.

This slice maps a JPEG Start-of-Scan boundary: entropy-coded scan bytes may contain byte-stuffed `0xff00`, restart markers, and delimiter-looking PDF text before the real EOI marker. The native DCT boundary must keep those bytes inside the image stream and expose review metadata without invoking Python, pypdfium/PIL, OCR, models, or external PDF tools.

## Red-First Gap

Before the source edit, the DCT text boundary already kept this payload out of visible text, but `extractImageXObjectBoundaryReview()` did not expose marker-level DCT stream-boundary metadata. The review row had `dctdecode_filter_boundary`, `raw_length`, and preview-only flags, but no evidence that JPEG SOI/SOS/EOI marker framing, byte-stuffed `0xff00`, or restart markers were recognized as image payload boundaries.

## Implementation

- Added `PdfTextExtractor::dctPreviewStreamBoundaryReview()` for direct DCT/JPEG review streams.
- The metadata records SOI/EOI offsets, recovered review length, whether the review stream was trimmed to EOI, SOS marker presence, byte-stuffed `0xff00`, restart markers, review-only status, and payload exclusion.
- Main Image XObject rows, explicit mask rows, and alternate image rows now include `dctdecode_stream_boundary` when the reviewed stream starts with a recoverable DCT/JPEG payload.

## Focused Evidence

```sh
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-sos-marker-boundary-currentbase.php
```

All returned no syntax errors.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php
```

Result: `1 test files, 39 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result: `6 test files, 1141 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-sos-marker-boundary-currentbase.php
```

The smoke emits `raw_length_recovered_past_fake_endstream=true`, `dctdecode_jpeg_marker_framing_used=true`, `dctdecode_sos_marker_seen=true`, `dctdecode_byte_stuffed_ff00_seen=true`, `dctdecode_restart_marker_seen=true`, `dctdecode_image_payload_excluded_from_text=true`, paragraphs `["DCT SOS Import","Clean DCT SOS Paragraph"]`, and all model/PDFium/PIL/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCT stale/missing/overdeclared Length recovery, APP-segment false-EOI handling, Flate/LZW/RunLength/ASCIIHex prefix recovery, indirect filter-owner recovery, null filter-slot DecodeParms alignment, Crypt Identity prefixes, post-DCT filter metadata, comment-split filter references, inline DCT tokenization, DCT CMYK/YCCK ColorTransform planning, or CCITT/JPX/JBIG2 preview-only boundaries.

The bounded behavior is specifically DCT/JPEG Start-of-Scan marker metadata for byte-stuffed scan data and restart markers while preserving existing image-payload exclusion.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF dictionary parser, stream-owner scanner, JPEG marker boundary scanner, Image XObject review path, and WordPress smoke renderer. Full JPEG raster decoding, pypdfium/PIL rendering, OCR, Surya/Texify/Torch model execution, and exact upstream image benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
