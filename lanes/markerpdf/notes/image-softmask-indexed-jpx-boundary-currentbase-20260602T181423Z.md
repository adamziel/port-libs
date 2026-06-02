# markerPDF Soft-Mask Indexed JPX Boundary

Session: `port-dev-markerpdf-image40pdf-20260602T181423Z`
Micro-slice: `image-softmask-indexed-jpx-boundary-currentbase-20260602T181423Z`
Base accepted HEAD: `babe129c590f2b2bc17296e92e8321e009789290`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF image rendering behind PDFium/PIL:

- `marker/pdf/images.py::render_image()` renders at `dpi / 72`, disables annotation drawing, and converts the rendered page to RGB.
- `marker/pdf/images.py::render_bbox_image()` crops the rendered RGB image.
- `marker/images/extract.py::extract_page_images()` inserts image Markdown spans for detected image regions.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The native PHP boundary is parser-side review before that RGB raster handoff:
Indexed palette and Decode metadata should be preserved, supported soft-mask
streams may be decoded for alpha review, but `/JPXDecode` image bytes remain
preview-only because this lane does not execute JPEG 2000 raster decoding,
pypdfium, PIL, Python models, or external PDF tools.

## Behavior

`PdfImageRenderer::indexedImageStreamPreviewRows()` now:

- decodes bounded Indexed image stream samples when filters are natively supported;
- maps stream samples through Indexed `/Decode`, palette lookup, and optional soft-mask alpha;
- reports JPX/JBIG2/CCITT raster image streams as review-only boundaries instead of throwing or claiming native raster decode;
- still decodes a supported soft-mask object from the current object map for alpha review metadata;
- exposes stream hashes, decoded lengths, preview-only filter lists, palette metadata, and stream notes for WordPress import review UIs.

`examples/wordpress-pdf-softmask-indexed-jpx-boundary-currentbase.php` models a WordPress PDF import review for an Indexed `/JPXDecode` image with a decoded grayscale soft-mask stream. It emits a Gutenberg image block with review metadata and no raster execution.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps decoded Indexed image stream rows and keeps JPX soft-mask streams review-only before RGB preview
1 test files, 443 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-softmask-indexed-jpx-boundary-currentbase.php
```

The smoke emitted `source_color_space=Indexed`, `image_filters=[JPXDecode]`,
`review_only_image_stream=true`, `indexed_high_value=3`,
`indexed_lookup_length_matches=true`, `soft_mask_decoded_with_current_filters=true`,
`soft_mask_decoded_preview_hex=0080FF`, `complete_soft_mask_sample_data=true`,
`stream_notes=[indexed_image_stream_preview_only_before_rgb_conversion, soft_mask_stream_filters_decoded_before_rgb_conversion]`,
and all execution flags false for Python/models, external PDF tools, pypdfium,
and PIL.

## Status Delta

- Focused behavior PASS rows: `633 -> 634`.
- Focused assertions in `PdfImageRendererTest.php`: `398 -> 443`.
- Added WordPress smoke: `examples/wordpress-pdf-softmask-indexed-jpx-boundary-currentbase.php`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Indexed ICC/JBIG2 palette review, Indexed default
Decode and decoded-index clipping, soft-mask `/Decode` opacity alone, soft-mask
transfer groups, inline JPX image scanning, DeviceN/ICCBased soft-mask stream
preview rows, DCTDecode CMYK Decode review, ColorKey mask suppression, or
generic image-filter text exclusion. The new behavior is specifically stream
preview/review metadata for XObject Indexed image streams, including the JPX
preview-only boundary with a separately decoded current-object soft mask.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value
parser, supported stream-filter decoders, Indexed palette reviewer, and
soft-mask alpha planner. Full live raster parity remains gated on pypdfium2/PIL
or a future native JPEG 2000/raster backend.
