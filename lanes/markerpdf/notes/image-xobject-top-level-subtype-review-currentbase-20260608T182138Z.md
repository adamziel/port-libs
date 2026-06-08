# Image XObject Top-Level Subtype Review Boundary - 2026-06-08

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T182138Z`
Base accepted HEAD: `74e2e1d508ba035b714146936835879271d84645`

## Scope

Ported a bounded native PDF parser boundary for Image XObject review metadata. Image stream selection already used top-level `/Type /XObject` and `/Subtype /Image` checks, but review rows reported `/Subtype` through a broad dictionary lookup. A nested private dictionary could therefore spoof primary image, SMask, Mask, Alternate, or Metadata stream subtype metadata after the stream had already been selected by the correct top-level boundary.

The patch adds a top-level name resolver for name values and applies it only to Image XObject review subtype fields.

## Evidence

Red-first focused command before source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelSubtypeReviewBoundaryCurrentBaseTest.php
```

Result: `1 test files, 13 assertions, 1 failures`; expected top-level subtype `Image`, actual nested private subtype `PS`.

Focused command after source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelSubtypeReviewBoundaryCurrentBaseTest.php
```

Result: `1 test files, 70 assertions, 0 failures`.

Adjacent image-boundary command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelSubtypeReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

Result: `5 test files, 1506 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-top-level-subtype-review-currentbase.php
```

Result: exits 0 with `primary_subtype=Image`, `soft_mask_subtype=Image`, `mask_subtype=Image`, `alternate_subtype=Image`, `metadata_stream_subtype=XML`, `nested_private_subtype_ignored=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object table, top-level dictionary scanner, strict indirect-name operand resolver, Image XObject review pipeline, and existing stream decoding helpers. GPU/model OCR, live raster rendering, pypdfium/PIL, external PDF tools, and exact upstream model benchmark parity remain intentionally excluded under the current no-GPU markerPDF scope.

## Non-Overlap

This does not repeat accepted DCTDecode marker-boundary, Image XObject dimension/type rejection, placement, CTM, optional-content, SMask decode, Mask ColorKey, Alternate image, or Metadata stream payload-leakage slices. It only tightens `/Subtype` review metadata to the same top-level boundary already used to admit Image XObject streams.
