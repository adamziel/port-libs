# markerpdf image XObject compound clip current-base handoff

- Session: port-dev-markerpdf-image-xobject-20260605T131843Z
- Micro-slice: markerpdf-image-xobject-boundary-current-base-20260605T131843Z
- Accepted base: 1316e4b8e007a66a99c6b69ce35b9a31ce98507b
- Scope: native no-GPU PDF parser/converter behavior under lanes/markerpdf only.

## Source truth

Upstream `sddai/markerPDF` keeps searchable text extraction separate from rendered image handling. This slice ports that boundary into the native PHP review path: Image XObject payloads are media-review metadata, not paragraph text, and PDF clipping paths apply before an image paint operation is considered visible.

No Python, PDFium, PIL, OCR, Surya, Texify, Torch, model workers, Streamlit/FastAPI workers, online services, or external PDF tools were run.

## Behavior

The focused gap was consecutive clipping-path intersections where the second clip is disjoint from the active clip. Before this patch, `PdfTextExtractor::pdfRectangleIntersection()` returned inverted coordinates such as `[40,40,20,20]`, which leaked invalid geometry into `invocation_clip_bboxes`.

This patch collapses disjoint intersections to a normalized zero-area rectangle and treats zero-area clips as fail-closed for text/image visibility. Image XObject review rows now expose `[40,40,40,40]` for the empty compound clip, report no painted invocation for that image, and still preserve a later valid compound clip with visible bbox `[10,10,40,30]`.

## Red-first evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`
- Result before source fix after adding the focused case: `1 test files, 762 assertions, 1 failures`.
- Failure: expected empty compound clip bbox `[[40,40,40,40]]`; actual was inverted `[[40,40,20,20]]`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` -> no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php` -> no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-compound-clip-currentbase.php` -> no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php` -> `1 test files, 786 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-compound-clip-currentbase.php` -> emits `empty_clip_bbox_normalized=true`, `empty_clip_excludes_image=true`, `empty_clip_painted_invocations=0`, `visible_clip_bbox=[10,10,40,30]`, `visible_clip_painted_invocations=1`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` -> clean.
- Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted image XObject payload exclusion, CTM placement, page/Form resource traversal, Contents arrays, rectangular/path clipping basics, optional content, artifact filtering, ExtGState, page rotation/UserUnit, image generation metadata, masks/SMask/Decode, or encrypted fail-closed review. The new behavior is specifically compound/consecutive clipping-path intersection normalization at the current accepted base.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP content-stream parser, clipping-path tracking, and image XObject review metadata path.

## Next task

Continue native no-GPU markerPDF work on non-overlapping parser fidelity: fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
