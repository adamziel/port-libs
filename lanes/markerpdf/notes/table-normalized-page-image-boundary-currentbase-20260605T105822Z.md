# markerpdf table normalized page-image geometry boundary current-base 2026-06-05T105822Z

## Scope

This isolated markerPDF slice adds the missing supplied-boundary handoff for
table recognition geometry serialized as 0-1000 coordinates relative to the
full rendered page image. The recognizer now scales those rows, columns, cells,
and OCR grid-border conflicts against the page image size and then translates
them into the cropped table image before assignment, Markdown formatting, and
WordPress review metadata.

This is intentionally distinct from the already accepted normalized table-crop
slice and the already accepted page-image translation slice:

- `normalized_table` and related aliases still scale directly to the table crop.
- `page_image` and related aliases still translate absolute page-image bboxes.
- New `normalized_page_image` aliases combine both operations using the table
  `image_bbox` / `page_image_bbox` / `rendered_image_bbox` extent as source
  truth, falling back to the supplied image size only when no page bbox exists.

## Source Truth

Local upstream source was used as source truth:

- `/tmp/markerpdf-upstream-src/tabled-0.1.4/tabled/inference/detection.py`
  crops high-resolution page images around detected table bboxes.
- `/tmp/markerpdf-upstream-src/tabled-0.1.4/tabled/inference/recognition.py`
  feeds table-local rows/cells into tabled assignment.
- `/tmp/markerpdf-upstream-src/tabled-0.1.4/tabled/extract.py` serializes each
  table result with the table bbox and full page image bbox.
- `lanes/markerpdf/src/BboxGeometry.php` already mirrors marker's 0-1000 bbox
  unnormalization contract.

## Implementation

- Added page-normalized coordinate-space aliases such as
  `normalized_page_image`, `page_image_normalized`,
  `normalized_rendered_page`, `normalized_full_page`,
  `normalized_pdf_page`, and `normalized_highres_page`.
- Added a recognizer path that:
  - unnormalizes source bboxes to full page-image coordinates;
  - records `source_page_image_bbox` review metadata;
  - translates the page-image bbox by the supplied table crop bbox;
  - counts the records as both normalized and translated in the coordinate-space
    review.
- Added OCR grid-border conflict handling for page-normalized conflict bboxes
  and candidate cell bboxes.
- Added a WordPress smoke proving stale pdftext inside the table block is
  replaced by the localized supplied table.

## Verification

Red-first probe before the recognizer patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedPageImageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL scales normalized page image table geometry before translating to the table crop
FAIL surfaces normalized page image table geometry through WordPress conversion metadata
1 test files, 3 assertions, 2 failures
```

After the recognizer patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometryNormalizedPageImageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS scales normalized page image table geometry before translating to the table crop
PASS surfaces normalized page image table geometry through WordPress conversion metadata
1 test files, 57 assertions, 0 failures
```

Focused table-geometry family:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableGeometry*CurrentBaseTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 626 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-table-normalized-page-image-boundary-currentbase.php
```

The smoke reports:

- `normalized_page_image_scaled_to_page=true`
- `normalized_page_image_translated_to_crop=true`
- `stale_page_normalized_cells_filtered=true`
- `excluded_stale_pdftext_table_line=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Syntax and patch hygiene:

```text
php -l lanes/markerpdf/src/TableRecognizer.php
No syntax errors detected in lanes/markerpdf/src/TableRecognizer.php

php -l lanes/markerpdf/tests/TableGeometryNormalizedPageImageBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/TableGeometryNormalizedPageImageBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-table-normalized-page-image-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-table-normalized-page-image-boundary-currentbase.php

git diff --check -- lanes/markerpdf
```

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
bbox normalization, supplied table recognition, table formatting, and
WordPress conversion boundaries. It does not invoke OCR, Surya, tabled models,
Python, GPU/model workers, external PDF tools, or live services.

## Next

Continue markerPDF's no-GPU supplied-boundary table work with non-overlapping
geometry handoffs such as rotated page-normalized table crops, equation
handoff geometry, or other parser-side searchable-PDF fidelity gaps.
