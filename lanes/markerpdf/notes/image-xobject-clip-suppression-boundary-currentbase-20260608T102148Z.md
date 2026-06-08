# markerPDF Image XObject Clip Suppression Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T102148Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T102148Z`
Base accepted HEAD: `a54545a529de1862e6e524e6822e40ce7f7c6600`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from page/image rendering. PDFium applies page graphics state, clipping, and transparency before Marker sees RGB image crops; the native no-GPU PHP lane records that boundary as review metadata without rasterizing.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now keeps clipping metadata separate from paint suppression metadata. A clipped Image XObject painted under `/ExtGState << /ca 0 >>` remains an invoked, review-only media row with its CTM bbox and active clip bbox, but it no longer reports `clip_excludes_image=true` or increments `clip_excluded_invocation_count`. The missing painted bbox is attributed to `graphics_state_paint_suppression_reasons=["nonstroking_alpha_zero"]`.

This preserves the existing clipped visible-image behavior while preventing WordPress media review from misclassifying transparent images as clipped-away images.

## Red First

Before the source fix, an ad hoc focused fixture produced:

```text
invocation_clip_bboxes=[[0,0,30,20]]
clip_excluded_invocation_count=1
clip_excludes_image=true
graphics_state_paint_suppression_reasons=["nonstroking_alpha_zero"]
painted_invocation_count=0
```

That showed the current source was counting transparent paint suppression as clip exclusion.

## Verification

Run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectClipSuppressionBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS separates clip metadata from transparent ExtGState image XObject suppression

1 test files, 39 assertions, 0 failures
```

Adjacent family to run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectClipSuppressionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectZeroAreaCtmBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateSoftMaskNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectExtGStateOverprintCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 1409 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-clip-suppression-currentbase.php
```

The smoke exits 0 and emits `clip_applied=true`, `clip_reduces_painted_bbox=false`, `clip_excludes_image=false`, `clip_excluded_invocation_count=0`, `graphics_state_paint_suppressed=true`, `graphics_state_paint_suppression_reasons=["nonstroking_alpha_zero"]`, `payload_in_visible_text=false`, and both Python/model/external-tool execution flags false.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectClipSuppressionBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-clip-suppression-currentbase.php
git diff --check -- lanes/markerpdf
```

## Non-overlap

This does not repeat accepted image XObject payload exclusion, CTM placement, Form XObject traversal, optional content, path clipping, transparent ExtGState suppression, SMask/Mask/Decode/filter metadata, ImageMask paint color, pattern images, malformed `Do` operands, inline image tokenization, encrypted fail-closed review, or OCR/model/raster execution. The bounded behavior is only classification of a clipped invocation whose painted bbox is absent because transparency suppresses paint, not because the clip path excludes the image.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, content tokenizer, resource dictionary parser, ExtGState review, clipping/CTM tracking, image XObject review rows, stream decoder, and WordPress smoke path. Full live raster parity remains gated on PDFium/PIL or a future native raster backend; this patch does not execute Python, OCR, models, external PDF tools, or raster rendering.
