# Image XObject Top-Level Dimension Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260607T002348Z`

Accepted base: `fcfc1289838c2e7d72110cd0e9fb80086fd87cb6`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image raster handoff. Native PHP Image XObject review metadata should therefore reflect the image stream dictionary that will be handed off for raster handling, not nested private dictionaries embedded inside that stream dictionary. This patch bounds `/Width`, `/Height`, `/BitsPerComponent`, `/StructParent`, and `/StructParents` reads to the top-level Image XObject dictionary while still allowing indirect numeric object operands.

## Behavior Added

- Primary Image XObject review rows now ignore nested private dimension decoys before reporting width, height, bit depth, and structure-parent metadata.
- Soft mask, explicit mask, and alternate image stream reviews now use the same top-level integer boundary for dimensions and bit depth.
- Raster payload bytes remain excluded from searchable text and review metadata still records native filter decode hashes.

## Evidence

Red-first focused test before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses only top-level Image XObject dimensions before review metadata and raster handoff
Expected: 2
Actual: 99
1 test files, 12 assertions, 1 failures
```

Focused test after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses only top-level Image XObject dimensions before review metadata and raster handoff
1 test files, 50 assertions, 0 failures
```

Focused Image XObject regression family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTopLevelDimensionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectResourceEntryTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormSubtypeNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectAlternatesBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1404 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-top-level-dimensions-currentbase.php
```

The smoke emits `top_level_dimensions_preserved=true`, `nested_dimension_decoys_excluded=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not change OCR, Surya/Texify/Torch, GPU/model execution, Streamlit/FastAPI workers, generic image raster decoding, placement CTM review, optional content, Form XObject resource traversal, resource-entry tail handling, or encrypted preflight behavior. It only closes the Image XObject dictionary boundary used for integer image metadata.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PDF dictionary scanner, object-resolution helper, stream filter handling, and Image XObject review pipeline. The no-GPU/model scope remains intentional.
