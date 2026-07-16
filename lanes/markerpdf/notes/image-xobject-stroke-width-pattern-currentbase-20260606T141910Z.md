# Image XObject Stroke-Width Pattern Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T141910Z`

Base accepted HEAD: `25518d5fb4d3eaf5d13a177b5560194ebcd2afa6`

## Source Truth

- Upstream markerPDF keeps PDF image rendering separate from searchable text extraction; this no-GPU lane preserves the native parser/review boundary and does not invoke raster, OCR, or model workers.
- PDF stroking state includes the `w` line-width operator. When a PatternType 1 resource is selected as the stroking color and a path is painted with `S`, the stroked paint area is wider than the path centerline and must bound nested Image XObject review/clipping.

## Behavior Added

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now tracks content-stream line width in image/pattern invocation graphics state.
- Stroke-painted tiling-pattern review expands pattern paint bboxes by half the effective line width before clipping nested Image XObject invocations.
- Review entries retain the original `pattern_path_bboxes` alongside expanded `pattern_bboxes`, plus `pattern_paint_kinds`, `pattern_stroke_widths`, and `pattern_stroke_width_expanded` metadata.
- Added a focused fixture where `6 w /Pattern CS /Line Stroke Tile SCN 0 0 m 20 0 l S` paints a one-dimensional line pattern whose nested Image XObject remains visible only because the stroke width creates a nonzero painted area.
- Added a WordPress smoke for the same stroked-line boundary and updated the existing stroking-pattern smoke to assert expanded paint bounds.

## Red-First Evidence

Before the source change, the new focused case failed because line width was not tracked:

```text
FAIL expands stroked tiling-pattern line bboxes by line width before image XObject review
Expected: array (0 => array (0 => 6.0,))
Actual: NULL
1 test files, 1208 assertions, 1 failures
```

## Focused Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS expands stroked tiling-pattern line bboxes by line width before image XObject review
1 test files, 1239 assertions, 0 failures
```

Smoke checks:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-stroking-pattern-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-stroke-width-pattern-currentbase.php
```

The new smoke emits `markerpdf:pdf-image-xobject-stroke-width-pattern-currentbase` metadata with `pattern_paint_kind=stroking`, `pattern_stroke_width=6`, `pattern_stroke_width_expanded=true`, `pattern_paint_bbox=[-3,-3,23,3]`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

- This slice extends the already accepted stroking tiling-pattern image review by adding line-width-expanded paint bounds.
- It does not repeat nonstroking tiling-pattern image traversal, marked-content propagation, optional-content hiding, ImageMask paint color review, Form XObject traversal, xref repair, stream filter decoding, encryption preflight, OCR/model execution, or external PDF rendering.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP content-token scanner, graphics-state normalization, path bbox tracking, PatternType 1 resource traversal, FlateDecode handling, and image boundary review metadata. GPU/model OCR, pypdfium, PIL, Python marker workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Next Task

Continue native no-GPU markerPDF image/filter review with non-overlapping behavior such as image filter metadata, pattern colorspace resource edge cases, stencil color boundaries, or page/resource inheritance gaps.
