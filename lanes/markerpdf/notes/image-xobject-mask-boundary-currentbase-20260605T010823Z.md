# markerPDF Image XObject Mask Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T010126Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T010126Z`
Base accepted HEAD: `70e9bdea1f1089cd9383d550be07b1b0df456263`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps page text extraction separate from image rendering: text comes through PDF text pages/pdftext, while page/crop image rendering goes through `marker/pdf/images.py` and RGB conversion before image insertion. The native no-GPU PHP path must therefore expose Image XObject alpha/mask details as review metadata, not visible WordPress paragraph text.

PDF image `/Mask` has two relevant parser boundaries:

- an indirect image mask stream, usually `/ImageMask true`, which contributes stencil alpha to the rendered image;
- a ColorKey `/Mask [min max ...]` array, which compares raw samples before `/Decode` and RGB preview conversion.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records:

- `mask_object`, `mask_review`, `mask_payload_in_visible_text`, and `mask_review_only` on Image XObject entries;
- indirect `/Mask` image streams with object number, dimensions, color space, image-mask state, `/Decode` opacity mapping, filter chain, raw length, decoded length/hash when native filters are safe, and payload exclusion;
- ColorKey `/Mask [...]` arrays with range pairs, component count, expected color-space component count, raw-sample comparison semantics, soft-mask suppression flag, and review-only status.

The existing `/SMask`, alternate-image, metadata-stream, Form XObject placement, clipping, optional-content, and payload-exclusion behavior is preserved.

## Verification

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 236 assertions, 1 failures
Failure: Undefined array key "mask_object"; Expected 6, Actual null
```

Passing focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 251 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted `first_mask_object=15`, `first_mask_type=image_mask_stream`, `first_mask_decoded_with_current_filters=true`, `first_mask_decode_inverted=true`, `hidden_marked_mask_type=color_key_mask_array`, `hidden_marked_color_key_valid=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Adds 1 focused PASS case to `PdfImageXObjectBoundaryCurrentBaseTest.php`.
- Focused assertion count for that file is now 251 assertions.
- Updates one existing WordPress image XObject smoke to include explicit `/Mask` stream and ColorKey mask-array review.

## Non-Overlap

This does not repeat accepted Image XObject fallback exclusion, CTM placement, Form XObject nesting, optional-content image review, image metadata streams, alternate images, `/SMask` filter-boundary decoding, ImageMask sample preview planning, or ColorKey sample preview in `PdfImageRenderer`.

The new behavior is specifically parser-level Image XObject boundary metadata for `/Mask` operands inside `PdfTextExtractor::extractImageXObjectBoundaryReview()`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, stream filter decoder, image stream recognizer, page resource walker, content-stream invocation tracker, and WordPress smoke path. Live raster parity remains out of scope under the no-GPU/no-model lane rule and would require a future native raster backend or PDFium/PIL-equivalent support component.
