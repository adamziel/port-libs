# markerPDF Image XObject Path Clip Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T113141Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T113141Z`
Base accepted HEAD: `5cc1cb8c4d627591b12d77b58e620af0751191d7`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from page/image rendering:

- `marker/pdf/extract_text.py` routes searchable text through the PDF text/pdftext path.
- `marker/pdf/images.py` renders page or bbox images through the image handoff path and returns image data outside the text pipeline.

This no-GPU native PHP slice maps the parser-side boundary for Image XObjects painted under path-constructed clipping paths. A renderer such as PDFium honors the current clipping path at the `Do` operator; the native importer reports the conservative visible bbox for media review without rasterizing the image or leaking stream bytes into text.

## Behavior

`PdfTextExtractor::applyClipPathStateOperator()` now tracks path current points and subpath start points while scanning content streams:

- `m`, `l`, and `h` update a current path bbox before `W` / `W*` applies clipping;
- curve operators `c`, `v`, and `y` conservatively union their operand/control points into the path bbox;
- path current/start points clear with ordinary path-painting/clearing operators;
- `q` / `Q` graphics-state scanners preserve the added path state for plain text, styled spans, marked-content segment extraction, and Image XObject invocation review.

The new test fixture paints an Image XObject through `10 10 m 40 10 l 40 30 l 10 30 l h W n ... /Path#20Clip#20Image Do`. The review row now reports `invocation_clip_bboxes=[[10,10,40,30]]` and `image_visible_bbox=[10,10,40,30]` while keeping the Flate image payload out of visible WordPress text and review JSON.

## Red First

Before the parser change, the new path-clipping fixture failed because only `re W n` rectangles contributed to the clip bbox:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL applies moveto lineto clipping paths to image XObject placement review
Expected: [[10,10,40,30]]
Actual: []
1 test files, 705 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 719 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-path-clip-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=1`, `invoked_image_xobject_count=1`, `resource_name="Path Clip Image"`, `image_unit_bbox=[0,0,50,40]`, `image_visible_bbox=[10,10,40,30]`, `clip_applied=true`, `clip_reduces_painted_bbox=true`, `clip_excludes_image=false`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-path-clip-currentbase.php
```

All three syntax checks reported no syntax errors.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `1777 -> 1778`.
- WordPress smoke scenarios: `1618 -> 1619`.
- Focused image XObject boundary suite: `705` assertions red-first with 1 failure, then `719` assertions green.
- Mapped upstream denominator: unchanged; this refines the already mapped image rendering/review boundary.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject inheritance, Contents array q/Q state, rectangular `re W n` clipping, metadata streams, alternates, masks, Decode arrays, SMask generation, named ColorSpace resources, private nested XObject boundaries, optional content, ExtGState transparency, page-box clipping, JPX `SMaskInData`, rotation/UserUnit geometry, artifact-marked invocations, malformed `Do` operands, or encrypted fail-closed review. The bounded behavior is only path-operator clipping before Image XObject boundary review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content tokenizer, matrix math, stream filter decoder, clipping state scanner, Form XObject traversal, image review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend, and live OCR/model execution remains intentionally out of scope under the current no-GPU markerPDF direction.
