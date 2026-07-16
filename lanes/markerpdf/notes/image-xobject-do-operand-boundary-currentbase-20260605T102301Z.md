# markerPDF Image XObject Do Operand Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T102301Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T102301Z`
Base accepted HEAD: `339f124190d9d276d42f196db494286344048c17`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering:

- `marker/pdf/extract_text.py` obtains text through the PDF text/pdftext path.
- `marker/pdf/images.py` renders page or bbox images through the image handoff path and returns RGB image data outside the text pipeline.

This native no-GPU PHP slice maps the parser-side PDF operator boundary for Image XObject review. A PDF `Do` XObject invocation has one resource-name operand. Malformed content such as `99 /MalformedImage Do` must not be treated as a painted image, even though the referenced Image XObject remains review-only metadata.

## Behavior

`PdfTextExtractor::xObjectNameOperand()` now returns a resource name only when the operator has exactly one name operand. This affects the shared XObject/ExtGState operand boundary used by Image XObject review and Form XObject text expansion:

- malformed extra-operand `Do` calls are kept as uninvoked Image XObject review rows;
- valid sibling `/ImageName Do` calls still carry CTM placement, decoded length/hash metadata, and payload exclusion;
- malformed image payload bytes remain excluded from WordPress visible text and review JSON.

## Red First

With the focused test added and the new guard temporarily removed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL rejects malformed image XObject Do invocations with extra operands
Expected: 1
Actual: 2
1 test files, 660 assertions, 1 failures
```

The old parser counted both `/MalformedImage` and `/ValidImage` as invoked images.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 681 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-do-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `malformed_extra_operand_unpainted=true`, `valid_sibling_image_painted=true`, `image_xobject_count=2`, `invoked_image_xobject_count=1`, `uninvoked_image_xobject_count=1`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, page/Form resource inheritance, optional content, OCMD, artifact suppression, clipping, page box clipping, rotation/UserUnit display geometry, exact-generation review, SMask/Mask metadata, ColorKey masks, named ColorSpace resources, ExtGState transparency review, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline-image tokenizer boundaries, top-level resource dictionary parsing, or PageLabels/parser stream-filter slices. The bounded behavior is only the single-name operand boundary before counting XObject invocations.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, resource dictionary parser, content tokenizer, Form XObject traversal, Image XObject review rows, stream decoders, and WordPress smoke renderer. Full upstream raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend, and live OCR/model execution remains intentionally out of scope under the current no-GPU markerPDF direction.
