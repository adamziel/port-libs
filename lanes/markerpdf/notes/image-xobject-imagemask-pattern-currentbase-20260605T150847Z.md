# markerPDF ImageMask XObject named paint resource boundary

Session: `port-dev-markerpdf-image-xobject-20260605T150847Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T150847Z`
Base accepted HEAD: `a11cd5344562a504795dcae9832876f83f36256e`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction in `marker/pdf/extract_text.py` separate from image rendering in `marker/pdf/images.py`, where page images are rendered separately and returned as RGB media artifacts.

PDF ImageMask XObjects are stencil masks: image bits define opacity and the painted color comes from the current nonstroking graphics state at the `Do` invocation. Under the no-GPU markerPDF scope, the native PHP lane records that boundary as review metadata without rasterizing or inserting image payload bytes into WordPress paragraphs.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now resolves ImageMask stencil paint colors that use named nonstroking color spaces and patterns:

- `/Brand#20RGB cs 0.1 0.25 0.9 scn` records `color_space=Brand RGB` and resolves the `/Resources /ColorSpace` entry to `resolved_color_space=DeviceRGB`.
- `/Pattern cs /Logo#20Pattern scn` records the pattern name, verifies it exists in `/Resources /Pattern`, and preserves an empty component list for a colored tiling pattern.
- Component counts are checked against the resolved graphics color space when the count is bounded.
- Existing DeviceGray, DeviceRGB, and DeviceCMYK `g`/`rg`/`k` paint-color rows stay unchanged.
- Visible text extraction still excludes ImageMask payload bytes and emits only the surrounding searchable text.

## Verification

Red-first focused check before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL records named and pattern image mask stencil paint color boundaries
Expected: 'DeviceRGB'
Actual: NULL
1 test files, 834 assertions, 1 failures
```

After the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 867 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-imagemask-pattern-currentbase.php
```

The smoke emitted `brand_stencil_color_space=Brand RGB`, `brand_stencil_resolved_color_space=DeviceRGB`, `brand_stencil_components=[0.1,0.25,0.9]`, `pattern_stencil_color_space=Pattern`, `pattern_stencil_pattern_name=Logo Pattern`, `pattern_stencil_resource_resolved=true`, `pattern_stencil_components=[]`, `payload_in_visible_text=false`, and no Python/model/external PDF tool execution.

## Status Delta

- Behavior tests: `2018 -> 2019` pass / `0` fail.
- Focused assertion count: `PdfImageXObjectBoundaryCurrentBaseTest.php` moves from `834` red-probe assertions to `867` passing assertions.
- WordPress scenarios: `1750 -> 1751`.
- Mapped semantics: adds `pdfImageXObjectImageMaskNamedPatternPaintColorCurrentBase`.

## Non-overlap

This does not repeat accepted image XObject payload exclusion, CTM placement, Form XObject traversal, optional content, ExtGState alpha/blend/soft-mask review, page geometry clipping, ImageMask opacity Decode review, inline ImageMask packed-sample rows, ColorKey masks, nested masks, CCITT/JBIG2/JPX/DCT filter boundaries, compound clipping paths, or the previous direct `g`/`rg`/`k` ImageMask paint-color slice.

The bounded behavior here is specifically named nonstroking `/ColorSpace` and `/Pattern` resource review for ImageMask XObject stencil invocations before WordPress media import.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, content-token parser, resource dictionary resolver, graphics-state stack, image XObject review rows, stream filter decoder, and WordPress smoke path.

Full raster parity with PDFium/PIL, OCR, Surya/Texify/Torch, and upstream model benchmark execution remains intentionally out of scope under the current no-GPU markerPDF override.
