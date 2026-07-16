# Image XObject Curve Path Current-Point Boundary

Slice: `markerpdf-image-xobject-boundary-current-base-20260608T143709Z`
Base accepted HEAD: `4f21f5a494acd2cdaafcccc96a3334aa48f5dae4`

## Source Truth

- Upstream markerPDF keeps image rendering/review behavior in the PDF image handoff path (`marker/pdf/images.py`) while searchable text extraction remains separate; this slice ports the native parser boundary without running PDFium, PIL, Python models, or OCR.
- PDF curve path operators `c`, `v`, and `y` require an active current path point. An orphan curve before `W n /Image Do` must not seed a clipping bbox. A valid `m ... c W n /Image Do` path still clips the image placement review bbox.

Upstream reference inspected: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Behavior

- `PdfTextExtractor::curvePathOperandPoints()` now returns no path points when curve operators appear without a current path point.
- Focused coverage proves orphan `c`, `v`, and `y` path operators leave Image XObject placement unclipped, while a valid current-point `m ... c` curve clip still reduces the visible bbox.
- Compressed image stream payload text remains excluded from `extractPlainText()` and the review JSON only exposes hashes/lengths.

## Red-First Evidence

Before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCurvePathBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores orphan curve path operators before image XObject clipping review
Expected: array ()
Actual: [[0.0,0.0,20.0,10.0]]
1 test files, 13 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCurvePathBoundaryCurrentBaseTest.php
1 test files, 86 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCurvePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPathOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectRectPathOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectRepeatedMovePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
5 test files, 1474 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-image-xobject-curve-path-currentbase.php
exits 0; reports orphan_c_clip_applied=false, orphan_v_clip_applied=false, orphan_y_clip_applied=false, valid_curve_clip_bbox=[150,0,170,10], payload_in_visible_text=false
```

## Non-Overlap

This does not repeat accepted q/Q graphics-state CTM placement, rectangular clipping, repeated move, rect path, path operand, nested Form XObject matrix, optional-content image visibility, Decode/Mask/SMask, malformed Do/cm, inline image tokenizer, or image filter metadata slices. It is a narrower path-current-point rule for curve operators before Image XObject clipping review.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP tokenizer, graphics-state matrix stack, path clipping tracker, stream filter decoder, Image XObject review metadata, and WordPress smoke path. Live OCR, Surya/Texify/Torch, GPU/model execution, PDFium/PIL raster parity, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
