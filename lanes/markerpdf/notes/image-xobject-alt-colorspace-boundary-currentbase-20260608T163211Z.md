# Image XObject Alternate Color-Space Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T163211Z`
Base accepted HEAD: `f7bb0ce56c95f19eaed5b64a386c252d4eb5269a`

## Behavior

Ported a native no-GPU Image XObject review boundary for alternate image color spaces. `PdfTextExtractor::extractImageXObjectBoundaryReview()` now recognizes `/Separation` and `/DeviceN` ColorSpace arrays, reports `color_space_component_count`, and uses that count to validate explicit `/Decode` arrays before RGB preview handoff. DeviceN component count is derived from the colorant name array, including indirect/resource-resolved color-space operands.

This matches the already-ported renderer-side support for Separation and DeviceN preview rows while keeping the parser/review path lightweight and fail-closed. The patch does not execute OCR, models, Python, PDFium, PIL, raster rendering, or external PDF tools.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateColorSpaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recognizes Separation and DeviceN image XObject color spaces before RGB review
Values are not identical
Expected: 'Separation'
Actual: NULL
1 test files, 7 assertions, 1 failures
```

Green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateColorSpaceBoundaryCurrentBaseTest.php
1 test files, 30 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectAlternateColorSpaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageDeviceNSeparationSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageCalibratedJbig2SoftMaskCurrentBaseTest.php
4 test files, 1395 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php
45 test files, 3168 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-alt-colorspace-boundary-currentbase.php
exits 0 and emits WordPress block/comment markup with image_xobject_count=2, invoked_image_xobject_count=2, spot_color_space=Separation, spot_component_count=1, devicen_color_space=DeviceN, devicen_component_count=2, valid Decode arrays, and payload_in_visible_text=false.
```

## Dependency Closure

No new support component is needed. This reuses the existing native PDF object, array, resource dictionary, stream filter, and Image XObject boundary helpers. Remaining OCR/model gaps stay intentionally out of scope under the no-GPU markerPDF override.

## Non-Overlap

This does not repeat the accepted Image XObject parent Form generation slice, recursive Form resource traversal, OPI proxy review, mask/SMask byte extraction, JPX SMaskInData, CTM/clip geometry, optional-content visibility, filter operand boundaries, or renderer-only Separation/DeviceN preview rows. It narrows the parser/review surface to alternate ColorSpace family recognition and Decode component-count validation for Image XObject metadata.
