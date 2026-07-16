# markerPDF DCTDecode renderer stream-boundary metadata

## Source Truth

Upstream markerPDF routes PDF image rendering through `marker/pdf/images.py::render_image`, where PDFium/PIL own raster decoding and searchable text extraction keeps image bytes out of visible text. Under the no-GPU native markerPDF scope, DCTDecode raster bytes remain review-only, but WordPress media-review consumers need the same JPEG marker-boundary proof that `PdfTextExtractor::extractImageXObjectBoundaryReview()` already exposes for DCT image XObjects.

## Behavior

`PdfImageRenderer` now records `dctdecode_stream_boundary` inside public `image_stream` metadata for direct DCTDecode image streams recovered through the preview-only boundary path. The renderer boundary records JPEG SOI/EOI offsets, SOS marker presence, byte-stuffed `0xff00`, restart markers, raw/review stream lengths, payload exclusion, review-only status, and native-raster-decode false without invoking Python, pypdfium/PDFium, PIL, OCR, models, or external PDF tools.

This is intentionally metadata-only at the native renderer boundary: it does not rasterize JPEG bytes and does not alter existing text extraction or DCT stream recovery decisions.

## Focused Evidence

Red-first before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL carries DCTDecode JPEG marker boundary into renderer image stream metadata (lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php)
Renderer DCT marker boundary should be present.

1 test files, 21 assertions, 1 failures
```

After the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS carries DCTDecode JPEG marker boundary into renderer image stream metadata

1 test files, 37 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-renderer-stream-boundary-currentbase.php
```

The smoke emits `renderer_dctdecode_jpeg_marker_framing_used=true`, `renderer_dctdecode_sos_marker_seen=true`, `renderer_dctdecode_byte_stuffed_ff00_seen=true`, `renderer_dctdecode_restart_marker_seen=true`, `dctdecode_image_payload_excluded_from_text=true`, clean paragraphs `["Renderer DCT Import","Clean Renderer DCT Paragraph"]`, and all Python/model/PDFium/PIL/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted direct DCTDecode text extraction recovery, missing/stale `/Length` recovery, prefix-filter DCT boundaries, Crypt Identity, unsupported prefix filters, malformed filter operands, DCT DecodeParms alignment, CMYK/YCCK sample planning, post-DCT filter reachability metadata, inline DCT tokenization, post-EOI surplus clipping, PDF comment terminators, or the existing extractor-side SOS marker metadata. The bounded new behavior is renderer-side propagation of DCT JPEG marker-boundary metadata into media-review `image_stream` rows.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, renderer stream filter metadata, DCT preview stream boundary recovery, and JPEG marker scanning. Full live raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend and is outside this no-GPU slice.
