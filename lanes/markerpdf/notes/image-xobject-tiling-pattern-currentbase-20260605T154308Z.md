# markerPDF Image XObject tiling-pattern boundary

Session: `port-dev-markerpdf-image-xobject-20260605T154308Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T154308Z`
Base accepted HEAD: `cc5990fba07cfe24ac4db3a1208b8183f8821c17`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering. Image resources are handled by `marker/pdf/images.py::render_image` and page-image discovery while text extraction must not import raster payload bytes as document text.

PDF tiling patterns are content-stream resource owners: a page can set `/Pattern cs /Name scn` and paint a path, while the PatternType 1 stream has its own `/Resources /XObject` dictionary and may invoke Image XObjects with `/Do`. Under the current no-GPU scope, markerPDF should expose those image boundaries as review metadata without rasterizing pattern cells.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now detects nonstroking tiling-pattern paints and recurses into PatternType 1 content streams:

- Resolves `/Resources /Pattern` entries and decodes their stream bodies when `/PatternType 1`.
- Tracks path-paint bboxes, pattern matrices, pattern visible bboxes, and parent pattern object/generation metadata.
- Traverses the pattern stream's private `/Resources /XObject` dictionary so invoked image XObjects receive CTM, unit bbox, clip bbox, and decoded hash metadata.
- Keeps uninvoked image resources inside the painted pattern stream as unpainted review rows.
- Preserves searchable WordPress text and excludes both painted and unpainted pattern-image payload bytes from visible text and JSON review output.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 903 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-tiling-pattern-currentbase.php
```

The smoke emits `image_xobject_count=2`, `invoked_image_xobject_count=1`, `uninvoked_image_xobject_count=1`, `pattern_resource_name=Image Tile`, `parent_pattern_object=11`, `pattern_paint_count=1`, `tile_image_unit_bbox=[4,6,10,9]`, `unused_pattern_image_unpainted=true`, `payload_in_visible_text=false`, and no Python/model/external PDF tool execution.

## Status Delta

- Behavior tests: `2044 -> 2045` pass / `0` fail.
- Focused assertion count: `PdfImageXObjectBoundaryCurrentBaseTest.php` now passes with `903` assertions.
- WordPress scenarios: `1767 -> 1768`.
- Mapped denominator coverage: unchanged; this is a focused current-base behavior/test growth slice.

## Non-overlap

This does not repeat accepted image XObject payload exclusion, CTM placement, Form XObject traversal, optional content, ExtGState alpha/blend/soft-mask review, page geometry clipping, ImageMask paint color review, inline ImageMask packed-sample rows, ColorKey masks, nested masks, CCITT/JBIG2/JPX/DCT filter boundaries, or compound clipping paths.

The bounded behavior here is specifically Image XObject traversal inside painted PatternType 1 tiling pattern resource streams.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, content-token parser, stream filter decoder, resource dictionary resolver, graphics-state stack, image XObject review rows, and WordPress smoke path.

Full raster parity with PDFium/PIL, OCR, Surya/Texify/Torch, and upstream model benchmark execution remains intentionally out of scope under the current no-GPU markerPDF override.
