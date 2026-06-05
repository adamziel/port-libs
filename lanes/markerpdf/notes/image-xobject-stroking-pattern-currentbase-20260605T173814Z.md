# Image XObject Stroking Pattern Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260605T173814Z`

Base accepted HEAD: `c6a8d542e199c5922210b1e1a777006ffcdcda14`

## Source Truth

- Upstream markerPDF image conversion ultimately hands image XObjects to image rendering/review paths, but this no-GPU lane keeps the native searchable-PDF parser boundary only.
- PDF content streams can select tiling patterns through stroking color operators (`CS`/`SCN`) and paint them with stroke operators such as `S`; image XObjects inside the PatternType 1 stream must be reviewed without exposing raster payload bytes as document text.

## Behavior Added

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now tracks stroking color state alongside the existing nonstroking color state while scanning content streams for tiling pattern paints.
- Pattern image traversal now recognizes stroke-painted pattern streams (`S`/`s`) and fill-stroke operators (`B`, `B*`, `b`, `b*`) without changing the accepted nonstroking fill behavior.
- Added a focused fixture where `/Pattern CS /Stroke Tile SCN ... S` paints a PatternType 1 stream that invokes one image XObject and leaves another pattern image resource uninvoked. The review keeps CTM, clip, visible bbox, decoded hash, and payload-exclusion evidence.
- Added a WordPress smoke for the same stroke-painted pattern boundary.

## Red-First Evidence

Before source changes, the new focused case failed:

```text
FAIL maps image XObjects painted from stroking tiling pattern streams as review-only metadata
Expected: 2
Actual: 0
1 test files, 927 assertions, 1 failures
```

The failure proves the current base did not traverse image resources in stroke-painted PatternType 1 streams.

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS maps image XObjects painted from stroking tiling pattern streams as review-only metadata
1 test files, 962 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-stroking-pattern-currentbase.php
```

The smoke emits `markerpdf:pdf-image-xobject-stroking-pattern-currentbase` metadata with `image_xobject_count=2`, `invoked_image_xobject_count=1`, `uninvoked_image_xobject_count=1`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

- This slice extends the existing image XObject PatternType 1 review from nonstroking fills to stroking pattern paints.
- It does not repeat the accepted nonstroking tiling-pattern fill test/example, ImageMask color review, optional-content image boundaries, Form XObject traversal, xref repair, stream filters, OCR/model execution, or external PDF rendering.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP content-token scanner, graphics-state normalization, tiling-pattern resource traversal, FlateDecode handling, and image boundary review metadata. GPU/model OCR, pdftext, pypdfium, PIL, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Next Task

Continue native no-GPU markerPDF image/filter review with non-overlapping behavior such as additional image filter metadata, stencil color edge cases, pattern colorspace resource resolution, or page-resource inheritance boundaries.
