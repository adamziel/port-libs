# markerpdf-image-xobject-boundary-current-base-20260608T075659Z

## Source Truth

- Upstream markerPDF separates searchable PDF text extraction from image handling: page text is imported as text, while image resources are routed through `marker.pdf.images.render_image`/RGB preview paths. Under the current markerPDF no-GPU scope, this slice ports the native parser boundary without running PDFium, OCR, Surya, Texify, Torch, PIL, or external PDF tools.
- PDF image stream dictionaries require unambiguous scalar dimensions and bits-per-component before raster handoff. Duplicate top-level `/Width`, `/Height`, or `/BitsPerComponent` declarations are ambiguous and must fail closed as review-only metadata.

## Behavior

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now treats duplicate top-level Image XObject `/Width`, `/Height`, and `/BitsPerComponent` declarations as `duplicate_top_level_declaration` numeric operand boundaries.
- Duplicate `/Width` or `/Height` keeps the image review row and decoded payload hash, but marks `image_dimensions_valid=false`, sets `native_raster_decode=false`, and records the offending operand boundary under `image_dimension_boundary`.
- Duplicate `/BitsPerComponent` keeps dimensions valid when they are otherwise valid, but records `bits_per_component_boundary`, sets `native_raster_decode=false`, and preserves payload exclusion from visible WordPress paragraphs.

## Evidence

- Red-first before implementation:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDuplicateNumericOperandCurrentBaseTest.php`
  - Result: `1 test files, 11 assertions, 1 failures`.
- Focused after implementation:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDuplicateNumericOperandCurrentBaseTest.php`
  - Result: `1 test files, 61 assertions, 0 failures`.
- Adjacent duplicate/numeric/dimension checks:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDuplicateNumericOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectNumericOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDuplicateSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php`
  - Result: `6 test files, 282 assertions, 0 failures`.
- Image-XObject family:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php`
  - Result: `35 test files, 2656 assertions, 0 failures`.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-duplicate-numeric-currentbase.php`
  - Result: exits 0; duplicate width/height/BPC rows have `native_raster_decode=false`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

- This does not repeat accepted Image XObject subtype, resource-name, numeric trailing-operand, invalid-dimension, zero-area CTM, Form XObject placement, optional-content, soft-mask, mask, Decode, OPI, or filter metadata slices.
- The patch is limited to duplicate top-level scalar declarations for Image XObject `/Width`, `/Height`, and `/BitsPerComponent`.

## Dependency Closure

- No new support component is needed. The slice reuses the native PDF dictionary parser, exact object resolution, existing stream decoding, and existing image XObject review metadata paths.
- No GPU/model/OCR dependency is introduced or exercised.

## Next

- Continue with non-overlapping native searchable-PDF behavior around image/filter metadata not covered by duplicate dimensions/BPC, fonts, CMaps, xref repair, metadata, annotations, forms, page geometry, or supplied-boundary table/equation handoffs.
