# Image XObject Reference Operand Boundary - Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T124843Z`
Base: `bcafca302a458fe3d8a05b35a98c1763065f1b98`

## Behavior

Image XObject adjunct operands that point to hidden streams must be single PDF operands. This patch makes `/SMask`, `/Mask`, and image-local `/Metadata` fail closed when a top-level reference operand has trailing tokens such as `20 0 R 99 0 R`. It also fails closed on indirect `/Mask` color-key array helper objects that contain an array plus trailing tokens.

Before the patch, a red probe showed `/SMask 6 0 R 99 0 R` was dereferenced as object `6`, populated `soft_mask_review`, and kept the parent image `native_raster_decode=true`. After the patch:

- `/SMask` returns `soft_mask_reference_operand_boundary`, keeps `soft_mask_object=null`, and blocks native raster handoff.
- `/Mask` returns `mask_reference_operand_boundary`, keeps `mask_object=null`, and blocks native raster handoff.
- `/Mask 23 0 R` where object `23` contains `[0 255 0 255 0 255] 99 0 R` returns `mask_array_operand_boundary` and blocks native raster handoff.
- `/Metadata` returns `rejected_malformed_image_xobject_metadata_stream_reference_operand`, does not decode the hidden metadata stream, and does not block native raster handoff because metadata is non-rendering review data.

Valid sibling image XObjects still decode with current native filters. Hidden image, mask, metadata, and decoy action payload text stays out of extracted WordPress paragraphs and out of review JSON.

## Source Truth

This is native no-GPU PDF parser behavior. PDF image dictionaries define these entries as single objects: `/SMask` is `/None` or an image XObject, `/Mask` is a color-key array or image XObject, and `/Metadata` is a metadata stream reference. Tailed operands are malformed and must not be treated as clean hidden-stream references.

Non-overlap: this slice avoids already accepted image XObject resource entry tails, numeric operand tails, duplicate numeric declarations, subtype boundaries, Form/Pattern BBox tails, metadata stream `/Filter` helper tails, and annotation/link destination operand boundaries.

## Verification

Red probe before fix:

- Accepted `/SMask 6 0 R 99 0 R` as `soft_mask_object=6`.
- Reported parent image `native_raster_decode=true`.

Focused verification after fix:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfImageXObjectReferenceOperandBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectReferenceOperandBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-reference-operand-boundary-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-reference-operand-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectReferenceOperandBoundaryCurrentBaseTest.php`
  - `1 test files, 83 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectReferenceOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMetadataFilterOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectImageMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectNumericOperandBoundaryCurrentBaseTest.php`
  - `5 test files, 271 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php`
  - `38 test files, 2826 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-reference-operand-boundary-currentbase.php`
  - exits `0`; summary reports `soft_mask_reference_operand_rejected=true`, `metadata_reference_operand_rejected=true`, `valid_sibling_native_raster_decode=true`, and `payload_excluded_from_text_and_review=true`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF token parsing helpers and the existing image XObject boundary review pipeline. It does not run OCR, Surya, Texify, Torch, raster model workers, external PDF tools, or live services.

## Next Task

Continue with non-overlapping native markerPDF parser behavior: image filter metadata, fonts/CMaps, xref repair, forms, page geometry, annotations, or supplied-boundary table/equation handoffs under the no-GPU scope.
