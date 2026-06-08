# DCTDecode SOF metadata boundary current-base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T224738Z`
Base: `c992bb947324f7207d596c6abc6496ba6a35dd32`

## Behavior

Added native DCTDecode JPEG SOF marker review metadata to both extractor and
renderer DCT stream boundary rows. The review now reports:

- `sof_marker_seen`
- `jpeg_sof_marker`
- `jpeg_precision`
- `jpeg_width`
- `jpeg_height`
- `jpeg_component_count`

The parser records only real SOF segment payloads while continuing to skip APP
segment payload bytes. This keeps fake `endstream` tokens, fake object bodies,
and nested false SOF marker bytes inside APP metadata out of visible text and
out of raster execution.

## Source truth and scope

Upstream markerPDF routes DCT image streams through image-size/raster handoff
logic in `marker.pdf.images.render_image`. This port keeps the no-GPU boundary
native and review-only: no Python, OCR/models, multiprocessing, PDFium/PIL, or
external PDF tooling is invoked.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSofMetadataBoundaryCurrentBaseTest.php
```

Result before source patch: `1 test files, 17 assertions, 1 failures`
because `sof_marker_seen` was absent.

Passing focused checks:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSofMetadataBoundaryCurrentBaseTest.php
```

Result: `1 test files, 35 assertions, 0 failures`

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeSofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSosMarkerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRestartIntervalBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodePaddedSegmentColorBoundaryCurrentBaseTest.php
```

Result: `4 test files, 139 assertions, 0 failures`

```bash
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-sof-metadata-boundary-currentbase.php
```

Result: exits `0` and reports `dctdecode_sof_marker_seen=true`,
`dctdecode_jpeg_width=37`, `dctdecode_jpeg_height=23`,
`dctdecode_jpeg_component_count=3`, and
`dctdecode_image_payload_excluded_from_text=true`.

## Dependency closure

No new support component is required. The patch reuses the existing native PHP
DCT/JPEG marker scanner and DCT stream review boundary pipeline.

## Non-overlap

This does not repeat accepted DCTDecode filter-array ownership, null slot,
DecodeParms alignment, malformed operand, DRI restart interval, SOS scan-data,
post-EOI, native-prefix, APP14 color-transform, or raster color conversion
coverage. It adds SOF dimension/precision/component review metadata only.
