# markerPDF Image XObject Zero-Area CTM Boundary

Session: `port-dev-markerpdf-image-xobject-20260607T042301Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260607T042301Z`
Base accepted HEAD: `b82f9244c643b3e715f941cde65b2e86a2a3ee98`

## Source Truth

Pinned upstream markerPDF keeps searchable PDF text extraction separate from image rendering: `marker/pdf/extract_text.py` routes text through PDF text extraction, while `marker/pdf/images.py` renders page and crop images outside the paragraph text path. Under the current no-GPU PHP lane scope, Image XObject payloads stay review-only metadata for WordPress media decisions.

PDF image painting uses the current transformation matrix to map an Image XObject's unit square into page space. A `/Name Do` under a zero-width or zero-height CTM is still an auditable XObject invocation, but it has no positive painted media area and should not produce a painted bbox.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now preserves zero-area Image XObject `Do` invocations while marking them as geometry-suppressed:

- `invoked=true` and `invocation_count=1` remain for the attempted paint;
- `painted_invocation_count=0` and `image_visible_bbox=null` report no painted media area;
- `geometry_paint_suppressed=true`, `geometry_paint_suppressed_invocation_count=1`, and `geometry_paint_suppression_reasons=["zero_area_ctm"]` distinguish this from clipping, optional content, or zero-alpha ExtGState suppression;
- visible sibling images still report normal painted bboxes;
- all raster payload bytes remain excluded from visible WordPress text and review JSON.

## Evidence

Red-first focused run after adding the regression and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectZeroAreaCtmBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "geometry_paint_suppressed" in .../PdfImageXObjectZeroAreaCtmBoundaryCurrentBaseTest.php on line 57
FAIL keeps zero-area CTM image XObject invocations reviewable but unpainted
Expected: true
Actual: NULL
1 test files, 13 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectZeroAreaCtmBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps zero-area CTM image XObject invocations reviewable but unpainted
1 test files, 47 assertions, 0 failures
```

Adjacent Image XObject boundary subset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*BoundaryCurrentBaseTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 1894 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-zero-area-ctm-currentbase.php
```

The smoke emits `zero_width_invoked=true`, `zero_width_painted_invocation_count=0`, `zero_width_suppression_reasons=["zero_area_ctm"]`, `zero_height_invoked=true`, `zero_height_painted_invocation_count=0`, `visible_image_painted=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, `Do` operand arity, `cm` operand arity, optional-content visibility, artifact suppression, path/page clipping, compound clip zero-area intersections, zero-alpha ExtGState suppression, Form/Pattern/Type3 XObject traversal, image dimensions validation, Mask/SMask/alternates/metadata/OPI review, DCT/CCITT/JPX/JBIG2 filter metadata, inline-image tokenizer behavior, or live raster execution. The bounded behavior is only zero-area placement geometry from the current transformation matrix before painted Image XObject bbox review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP content-stream tokenizer, graphics-state CTM helpers, Image XObject review collector, stream decoders, focused PHP tests, and WordPress smoke path. Full rendered-image parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, external PDF tools, and exact upstream model benchmark parity were not run.
