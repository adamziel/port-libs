# markerPDF Image XObject Form Matrix Indirect Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T105721Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T105721Z`
Base accepted HEAD: `dcffe1acc4e49acf2f537c3ed1ff1114ba732d69`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from page/image rendering. Visible text comes through `marker/pdf/extract_text.py`, while images are rendered from PDF page geometry through `marker/pdf/images.py::render_image()` / `render_bbox_image()` before any model-side recognition.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

This native PHP slice stays at that parser/review boundary. It does not rasterize the image; it preserves the PDF page-space image placement metadata needed before a future renderer or WordPress media review consumes the bounding box.

## Native Behavior Added

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now resolves indirect numeric operands in Form XObject `/Matrix` arrays before concatenating the Form transform with nested Image XObject `cm` placement.

The focused fixture paints `/Nested Matrix Image` through `/Matrix Form`, where the Form dictionary has:

```text
/Matrix [21 0 R 22 0 R 23 0 R 24 0 R 25 0 R 26 0 R]
```

and objects `21` through `26` resolve to `[1 0 0 1 3 4]`. The review row now reports:

- `resource_path=["Matrix Form","Nested Matrix Image"]`
- `invocation_matrices=[[120,0,0,30,370,380]]`
- `invocation_bboxes=[[370,380,490,410]]`
- `image_unit_bbox=[370,380,490,410]`

The nested image stream payload remains review-only and absent from visible WordPress text.

## Evidence

Red-first focused probe before the fix:

```text
indirect Form /Matrix operands produced invocation_matrices=[[2520,0,1320,0,9850,200]]
and invocation_bboxes=[[9850,200,13690,200]]
```

Expected geometry after resolving `[1 0 0 1 3 4]`:

```text
invocation_matrices=[[120,0,0,30,370,380]]
invocation_bboxes=[[370,380,490,410]]
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-matrix-indirect-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-matrix-indirect-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 698 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-matrix-indirect-currentbase.php
```

The smoke emits `form_matrix_indirect_operands_resolved=true`, `nested_invocation_matrix=[120,0,0,30,370,380]`, `nested_image_unit_bbox=[370,380,490,410]`, `payload_in_visible_text=false`, and the two expected paragraph blocks.

Whitespace:

```text
git diff --check -- lanes/markerpdf
```

No output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, direct CTM placement, direct Form `/Matrix` placement, graphics-state preservation across Contents arrays, rectangular clipping, optional-content image hiding, artifact-marked images, page box/UserUnit clipping, rotated page geometry, filter/Decode/mask/SMask/metadata/alternate review, exact-generation auxiliary stream review, named ColorSpace resolution, ExtGState review, JPX `SMaskInData`, or malformed extra-operand `Do` rejection.

This slice owns only the native PDF parser boundary where a Form XObject matrix array is syntactically direct but its six numeric operands are indirect objects.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object resolver, numeric array parser, Form XObject resource traversal, CTM composition, and image review metadata path.

No GPU/model/OCR work was run. Live OCR, Surya/Texify/Torch execution, pypdfium/PIL raster parity, and exact upstream image-rendering benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
