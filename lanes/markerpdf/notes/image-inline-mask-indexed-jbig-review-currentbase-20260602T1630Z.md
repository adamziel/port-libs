# markerPDF Inline Mask Indexed JBIG Review

Session: `port-dev-markerpdf-image30pdf-20260602T1630Z`
Micro-slice: `image-inline-mask-indexed-jbig-review-currentbase-20260602T1630Z`
Base accepted HEAD: `ce4d02651156db0ca80cec00a035bd5f5795584e`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` renders PDF page/crop images through `marker/pdf/images.py::render_image()` and `render_bbox_image()`: PDFium renders the page and PIL converts the result to RGB before `marker/images/extract.py::extract_page_images()` inserts image Markdown. Native PHP still does not execute PDFium/PIL, so the source-truth boundary for this slice is parser-side image review metadata before any future raster backend runs.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/images/extract.py

## Native Behavior Added

`PdfImageRenderer::inlineImageReviewPlan()` now expands inline image key/value abbreviations (`/W`, `/H`, `/CS`, `/BPC`, `/F`, `/DP`, `/D`, `/IM`, `/I`, `/RGB`, `/G`, and filter abbreviations) into an XObject-compatible dictionary before calling the existing image review planner.

The returned review metadata records:

- canonical inline-image dictionaries and payload hashes/previews;
- Indexed palette lookup metadata, `/Decode` index mapping, and JBIG2 preview-only filter state including `/JBIG2Globals`;
- `/ImageMask true` stencil metadata, inverted `/Decode` opacity for zero/one samples, and payload exclusion from visible text;
- explicit `inline_image_payload_excluded_from_text` and `inline_image_review_only` flags.

`examples/wordpress-pdf-inline-mask-indexed-jbig-review-currentbase.php` models a WordPress import with an inline Indexed/JBIG2 payload and an inline JBIG2 ImageMask payload. Both payloads contain text-looking PDF operators, but the imported paragraph text is only `Before Inline Image Review` and `After Inline Image Review`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php
1 test files, 263 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-mask-indexed-jbig-review-currentbase.php
```

The smoke emitted `visible_text_imported=true`, `excluded_inline_indexed_payload_text=true`, `excluded_inline_mask_payload_text=true`, `indexed_preview_only_filters=["JBIG2Decode"]`, `indexed_jbig2_globals_present=true`, `mask_source_color_space=ImageMask`, `mask_decode_inverted=true`, `mask_opacity_zero=1`, `mask_opacity_one=0`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Syntax and JSON checks passed for:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfImageRendererTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-mask-indexed-jbig-review-currentbase.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
```

`git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Behavior tests: `558 -> 559`.
- Mapped semantics: `399 -> 400 / 78`.
- Focused image renderer test: `234 -> 263` assertions in `PdfImageRendererTest.php`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted XObject Indexed ICCBased/JBIG2 soft-mask review, base image `/Decode`, `/ImageMask` XObject stencil opacity, soft-mask `/Decode`, DCT CMYK Decode, Separation/DeviceN alternate-color preview, ICC soft-mask validation, inline image DecodeParms EI validation, or generic JPX/JBIG2 image-filter text exclusion.

The new behavior is specifically inline-image review planning for abbreviated Indexed/JBIG2 and ImageMask dictionaries, with payload exclusion preserved for the WordPress import path.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser, existing image color/filter/Decode planners, and native content-stream inline image exclusion. Full live raster parity remains gated on pypdfium2/PIL or a future native raster backend; this patch does not execute Python, models, pdftext, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools.
