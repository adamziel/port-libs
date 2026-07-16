# Image XObject SMask Exact-Generation Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260605T020804Z`
Base: `927c0bebf9176d6d86819fbec882fef400f8d3f6`
Date: 2026-06-05 UTC

## Source Truth

- PDF Image XObjects may carry `/SMask` as a soft-mask Image XObject stream or `/SMask /None`.
- Soft masks are alpha review/raster metadata, not searchable text. Their stream payload must not be appended to WordPress paragraph text.
- Indirect PDF references are generation-qualified. A `/SMask 6 1 R` boundary must resolve generation `1`, not a stale `6 0` body with the same object number.
- This is parser-side `PdfTextExtractor::extractImageXObjectBoundaryReview()` behavior. It does not overlap the existing renderer-only soft-mask raster fallback note.

## Implementation

- Added `soft_mask_generation`, `soft_mask_review`, `soft_mask_payload_in_visible_text`, and `soft_mask_review_only` to Image XObject boundary entries.
- Added exact-generation `/SMask` resolution through the current object owner/direct-generation maps.
- Added review metadata for soft-mask streams: dimensions, colorspace, bits, Decode review, opacity endpoints, filter stack, decoded length/hash, and review-only payload flags.
- Added explicit `/SMask /None` review metadata so opaque images are distinguishable from images with no soft-mask key.
- Preserved existing `/Mask` color-key suppression semantics only when a real soft-mask stream is present.

## Focused Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

Result: failed on missing `soft_mask_generation` for the new SMask exact-generation case.

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

Result: `1 test files, 293 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

Result: emits `first_soft_mask_object=16`, `first_soft_mask_generation=1`, `first_soft_mask_type=soft_mask_stream`, `first_soft_mask_decode_inverted=true`, `stale_soft_mask_generation_rejected=true`, and paragraph output excludes image/mask/soft-mask payload text.

## Non-Overlap

Avoided prior accepted/ready image-filter clusters:

- DCTDecode filter/segment EOI boundary work.
- Image XObject `/Mask` stream and ColorKey review behavior.
- Renderer-side SMask fallback/raster decode behavior.
- Image XObject placement, metadata stream, alternate image, optional-content, and exact resource-generation review behavior.

## Dependency Closure

No new support component is required. The slice reuses existing native PHP parser primitives: top-level dictionary value parsing, exact indirect-object generation lookup, stream filter decoding, Decode-array review, and image colorspace component counting.

Root harness: not run - isolated micro-slice.
