# markerPDF ImageMask XObject paint-color boundary

Session: `port-dev-markerpdf-image-xobject-20260605T142925Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T142925Z`
Base accepted HEAD: `bf75562f447c1c8f603ede7bf5edd88ff3917f71`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction in `marker/pdf/extract_text.py` separate from image rendering in `marker/pdf/images.py`, where page images are rendered separately and returned as RGB media artifacts.

PDF ImageMask XObjects are stencil masks: their image bits define opacity and the painted color comes from the current nonstroking graphics state. Under the no-GPU markerPDF scope, the native PHP lane records that boundary as review metadata without rasterizing or inserting image payload bytes into WordPress paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now carries nonstroking color graphics-state operators through Image XObject `Do` invocation collection:

- `g`, `rg`, and `k` update DeviceGray, DeviceRGB, and DeviceCMYK stencil paint colors.
- `cs`, `sc`, and `scn` update the current nonstroking color-space/value boundary for future review slices.
- `q`/`Q` preserve and restore the current paint color with the rest of the invocation state.
- ImageMask entries expose `image_mask_paint_colors` with color space, components, pattern name, source operator, and `review_only=true`.
- Non-ImageMask XObjects remain image-only review rows and visible text extraction still excludes raster payload bytes.

The focused fixture paints one ImageMask after `0.25 0.5 0.75 rg` and another after `0.4 g`. WordPress text contains only the surrounding text, while review metadata records DeviceRGB and DeviceGray stencil colors.

## Verification

Red-first focused check before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL records image mask stencil paint color at XObject invocation boundaries
Expected: true
Actual: NULL
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 828 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-imagemask-color-currentbase.php
```

The smoke emitted `blue_stencil_color_space=DeviceRGB`, `blue_stencil_components=[0.2,0.4,0.8]`, `gray_stencil_color_space=DeviceGray`, `gray_stencil_components=[0.35]`, `payload_in_visible_text=false`, `image_mask_paint_color_review_only=true`, and no Python/model/external PDF tool execution.

## Status Delta

- Behavior tests: `1994 -> 1995` pass / `0` fail.
- Focused assertion count: `PdfImageXObjectBoundaryCurrentBaseTest.php` moves from `809` to `828` assertions.
- WordPress scenarios: `1728 -> 1729`.
- Mapped semantics: adds `pdfImageXObjectImageMaskPaintColorCurrentBase`.

## Non-overlap

This does not repeat accepted image XObject payload exclusion, CTM placement, Form XObject traversal, optional content, ExtGState alpha/blend/soft-mask review, page geometry clipping, ImageMask opacity Decode review, inline ImageMask packed-sample rows, ColorKey masks, nested masks, CCITT/JBIG2/JPX/DCT filter boundaries, or compound clipping paths.

The bounded behavior here is specifically current nonstroking paint-color review for ImageMask XObject stencil invocations before WordPress media import.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, content-token parser, graphics-state stack, image XObject review rows, stream filter decoder, and WordPress smoke path.

Full raster parity with PDFium/PIL, OCR, Surya/Texify/Torch, and upstream model benchmark execution remains intentionally out of scope under the current no-GPU markerPDF override.
