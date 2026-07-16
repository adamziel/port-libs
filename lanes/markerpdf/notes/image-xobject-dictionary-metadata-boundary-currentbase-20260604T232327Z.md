# markerPDF Image XObject Dictionary Metadata Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260604T232327Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260604T232327Z`
Base accepted HEAD: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image rendering:

- `marker/pdf/extract_text.py` routes text through `pdftext.extraction.dictionary_output` and PDFium text pages.
- `marker/pdf/images.py` renders pages with pypdfium, disables annotation drawing, converts output to RGB, then crops page images for image/equation regions.

Under the current no-GPU lane scope, the PHP port owns the native parser boundary before any future raster backend. Image XObject dictionary metadata belongs in review metadata for WordPress media import, not in visible Gutenberg paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records image dictionary review fields for page and Form-painted Image XObjects:

- `/Interpolate` as `interpolate`;
- `/Intent` as `rendering_intent`;
- `/Name` as `image_name`;
- `/StructParent` and `/StructParents` as structure-parent review keys;
- image-local `/Metadata` stream object, subtype, filters, raw length, decoded length/hash when current filters are natively supported, and explicit review-only/payload-excluded flags.

The metadata stream payload is not serialized into the review JSON and does not enter visible WordPress text. Existing image placement, optional-content, clipping, nested Form XObject, filter, and payload-exclusion behavior is preserved.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records image XObject dictionary metadata without leaking metadata streams into text
Values are not identical
Expected: true
Actual: NULL
PHP Warning:  Undefined array key "interpolate" .../PdfImageXObjectBoundaryCurrentBaseTest.php on line 466
1 test files, 191 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records image XObject dictionary metadata without leaking metadata streams into text
1 test files, 201 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
```

The smoke emitted `image_xobject_count=3`, `invoked_image_xobject_count=1`, `first_interpolate=true`, `first_rendering_intent=RelativeColorimetric`, `first_image_name=Hero Image`, `first_struct_parent=8`, `first_struct_parents=9`, `first_metadata_object=11`, `first_metadata_subtype=XML`, `first_metadata_filters=["FlateDecode"]`, `first_metadata_decoded_with_current_filters=true`, `payload_in_visible_text=false`, and only the two expected Gutenberg paragraph blocks.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `1102 -> 1103` pass / `0` fail.
- Focused assertion count for `PdfImageXObjectBoundaryCurrentBaseTest.php`: red-first `191` assertions with `1` failure, then `201` assertions / `0` failures.
- WordPress scenario count: `1101 -> 1102`.
- Mapped upstream denominator: unchanged; this refines the existing `pdfImageXObjectBoundaryBehaviors` row.

## Non-Overlap

This does not repeat accepted page image resource review, nested Form XObject image discovery, resource-less Form inheritance, optional-content visibility, CTM placement, rectangular clipping/Form BBox intersection, inline image parsing, DCT/CCITT/JPX/JBIG2 preview-only filter boundaries, soft-mask/color-space/Decode previews, or XMP document metadata extraction.

The bounded behavior here is specifically Image XObject dictionary metadata and image-local `/Metadata` stream review while preserving text isolation before WordPress import.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, image resource resolver, stream dictionary parser, stream filter decoder, content-token text extractor, and WordPress smoke renderer. Full upstream raster parity remains dependency-gated by PDFium/pypdfium and PIL image conversion; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and external PDF tools were not run.
