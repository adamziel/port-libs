# markerPDF Image XObject Transparent ExtGState Boundary Current Base

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image rendering. Page images are rendered through the PDF image/RGB handoff, while text extraction must not promote raster payload bytes into WordPress paragraphs.

For this native no-GPU PHP lane, an Image XObject painted under a graphics state with `/ca 0` remains an auditable `Do` invocation but contributes no painted media bbox. The import review should keep the resource, placement matrix, ExtGState metadata, and decoded hash, while reporting zero painted invocations and no visible bbox.

## Implementation

`PdfTextExtractor::contentXObjectInvocationDetails()` now evaluates the current invocation graphics state before computing visible image bboxes. If the nonstroking alpha constant is zero, it preserves the invocation matrix and raw bbox but suppresses the visible bbox with `graphics_state_paint_suppression_reason = nonstroking_alpha_zero`.

`PdfTextExtractor::imageXObjectBoundaryEntry()` now exposes:

- `graphics_state_paint_suppressed`;
- `graphics_state_paint_suppressed_invocation_count`;
- `graphics_state_paint_suppression_reasons`.

The existing nested image review expectations were also refreshed for `dctdecode_stream_boundary => null`, matching the current source shape for alternate image and mask stream review rows.

## Red First

After adding the focused case and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`

Result: `1 test files, 1014 assertions, 1 failures`.

The failing assertion was the transparent image `painted_invocation_count`: expected `0`, actual `1`.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`

Result: `1 test files, 1041 assertions, 0 failures`.

Focused image-XObject regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`

Result: `3 test files, 1092 assertions, 0 failures`.

Syntax checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-transparent-extgstate-currentbase.php`

All reported no syntax errors.

The WordPress smoke is:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-transparent-extgstate-currentbase.php`

It emits normal paragraph text plus review metadata with `transparent_invoked=true`, `transparent_painted_invocation_count=0`, `transparent_paint_suppression_reasons=["nonstroking_alpha_zero"]`, `visible_painted_invocation_count=1`, and both Python/model/external-tool execution flags false.

Diff check:

`git diff --check -- lanes/markerpdf`

Result: passed.

A broader sampled run including `PdfTextExtractorTest.php` was not counted as this slice's verification because that file still has two pre-existing ToUnicode `usecmap` expectation failures unrelated to the Image XObject invocation scanner.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject traversal, optional content, artifact suppression, clipping/page geometry, ExtGState metadata recording, SMask/Mask/Decode/filter metadata, ImageMask paint-color review, pattern image paints, malformed `Do` operands, inline image tokenization, encrypted fail-closed review, or OCR/model/raster execution. The bounded behavior is specifically fully transparent nonstroking ExtGState alpha at the Image XObject invocation boundary.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, resource dictionary parser, ExtGState review, content-stream tokenizer, graphics-state matrix/bbox tracking, stream decoder, Image XObject review rows, and WordPress smoke path. Full upstream raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and other external PDF tools were not run.
