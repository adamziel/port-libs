# Image XObject Marked-Content Operand Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T054731Z`  
Accepted base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Source Truth

- Upstream markerPDF keeps searchable text extraction and image rendering as separate handoffs; native PHP review metadata must not promote raster payload bytes into extracted WordPress text.
- PDF marked-content spans use fixed `BMC`/`BDC` operand shapes. A malformed image-content span must not be trusted as an `/Artifact` suppression boundary, an optional-content hiding boundary, or a `/Figure` metadata source.
- Existing accepted markerPDF coverage already handled valid image Artifact spans, valid Figure spans, q/Q marked-content preservation, clipping, patterns, forms, resource wrappers, generation exactness, and image payload exclusion. This slice is limited to malformed BMC/BDC operand validation for Image XObject review.

## Behavior

- Added `markedContentSpanOperandsAreWellFormed()` and reused it for Image XObject invocation metadata and artifact marked-content filtering.
- Valid `/Artifact BMC` and `/Artifact << ... >> BDC` spans still suppress decorative image invocations.
- Malformed `/Artifact 99 << ... >> BDC` spans no longer hide the image invocation.
- Malformed `/Figure 99 << ... >> BDC` spans no longer attach trusted `Alt`, `ActualText`, or `MCID` metadata to the image review row.
- Malformed `777 /OC /LayerOff BDC` spans no longer hide Image XObject invocations through stale operands.
- Raster/image payload bytes remain excluded from extracted text and review JSON.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentOperandBoundaryCurrentBaseTest.php` failed before implementation with `Expected: 2`, `Actual: 1` for `invoked_image_xobject_count` after 5 assertions for malformed Artifact suppression; after expanding the case, it also failed with `Expected: 3`, `Actual: 2` after 5 assertions for malformed optional-content hiding.
- Focused after fix: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentOperandBoundaryCurrentBaseTest.php` passed, `1 test files, 53 assertions, 0 failures`.
- Adjacent image-boundary run: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectMarkedContentQRestoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php` passed, `2 test files, 1290 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-marked-content-operand-boundary-currentbase.php` exited 0 with `image_xobject_count=4`, `invoked_image_xobject_count=3`, `uninvoked_image_xobject_count=1`, malformed artifact invoked, valid artifact suppressed, malformed figure metadata ignored, malformed optional content invoked/visible, and payloads excluded from text/review JSON.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, marked-content resource lookup, Image XObject review path, and Flate stream decoding already in `lanes/markerpdf/src/PdfTextExtractor.php`. GPU/model/OCR/Python worker parity remains intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

Avoided accepted image XObject work around CTM placement, clipping paths, valid optional content, valid Artifact/Figure spans, pattern paints, Type3 CharProcs, resource wrappers, stream filters, masks, OPI/alternate streams, page geometry, and generation exactness. The only production behavior changed here is malformed BMC/BDC operand trust at the Image XObject review boundary.
