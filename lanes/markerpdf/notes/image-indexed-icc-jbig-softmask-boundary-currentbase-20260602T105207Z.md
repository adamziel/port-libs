# markerPDF Indexed ICCBased JBIG2 Soft-Mask Boundary

Session: `port-dev-markerpdf-imagepdf-20260602T105207Z`
Micro-slice: `image-indexed-icc-jbig-softmask-boundary-currentbase-20260602T105207Z`
Base accepted HEAD: `3ee94b2b9b3e6147faa2f27766c75d7097a754ae`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders image crops through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders at `scale=dpi / 72`, disables annotations, and PIL converts the result to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown spans.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

This native PHP slice keeps that RGB-preview boundary without pypdfium/PIL. It records the PDF parser decisions that must happen before a future raster backend: Indexed palette lookup, ICCBased base profile metadata, JBIG2 preview-only filter handling, and soft-mask alpha decode.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits:

- `image_filters` and `image_filter_boundary` metadata, including JBIG2 preview-only filters and `/JBIG2Globals` presence.
- `uses_indexed_color_space` and `indexed_color_space` metadata for `/Indexed` color spaces, including indirect ICCBased base color spaces, `/hival`, lookup source, lookup length, expected length, lookup bytes, and mismatch state.
- ICC profile metadata propagated from an Indexed base color space.
- Existing soft-mask `/Decode` alpha metadata alongside the Indexed/JBIG2 boundary.

`PdfImageRenderer::indexedSampleToBaseComponents()` expands a decoded Indexed sample into normalized base color components, guarded by the declared high value and lookup length.

## Evidence

Red-first focused failure after adding the test, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 104 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 121 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-indexed-icc-jbig-softmask-review.php
```

The smoke emits `source_color_space=Indexed`, `image_filters=[JBIG2Decode]`, `preview_only_filters=[JBIG2Decode]`, `jbig2_globals_present=true`, `indexed_base_color_space=ICCBased`, `indexed_lookup_length_matches=true`, `decoded_index=2`, `soft_mask_opacity_zero=1`, `soft_mask_opacity_max=0`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax, JSON, and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-indexed-icc-jbig-softmask-review.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

All passed locally.

## Status Delta

- Focused assertions: `100 -> 121` in `PdfImageRendererTest.php`.
- Behavior tests: `474 -> 475`.
- Mapped semantics: `324 -> 325 / 78`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ICCBased profile parsing, soft-mask presence and `/Matte` metadata, soft-mask `/Decode` opacity alone, base image `/Decode` sample mapping, `/ImageMask` stencil opacity, DCTDecode CMYK Adobe-transform planning, JPX/JBIG2 text-extraction filter exclusion, inline-image payload boundaries, or image stream fallback exclusion. The new behavior is specifically the preview-planning boundary where an Indexed image uses an ICCBased base color space, a JBIG2 raster filter, and a soft mask.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser and `PdfImageRenderer` review planner. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, or raster rendering.
