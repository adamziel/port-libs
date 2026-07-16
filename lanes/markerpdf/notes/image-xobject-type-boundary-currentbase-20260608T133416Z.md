# Image XObject Type Boundary Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T133416Z`

Accepted base: `ab39c48b2a82ff9622403db018d37fcff9180477`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image raster handoff. Under the current no-GPU markerPDF scope, this lane owns the native PDF parser boundary before an Image XObject would be passed to the image review/rendering path.

PDF Image XObjects are XObject streams with `/Subtype /Image`; an explicit `/Type` is optional, but when present it must identify the stream as an `/XObject`. This slice makes the native image handoff fail closed for explicit non-XObject Type values, literal Type values, tailed Type operands, and duplicate Type declarations while preserving existing Type-less `/Subtype /Image` dictionary fallback.

## Behavior

- `PdfTextExtractor::isImageStreamDictionary()` now validates a top-level `/Type` before Image XObject review.
- Duplicate `/Type`, `/Type /Metadata`, `/Type (XObject)`, and `/Type /XObject 99 0 R` image-looking streams are excluded from image review rows and decoded hash metadata.
- Type-less image dictionaries and valid `/Type /XObject /Subtype /Image` dictionaries still record invocation matrices, bboxes, native filter decode hashes, and review-only metadata.
- Raster payload bytes remain excluded from searchable text and WordPress paragraph output.

## Red First

Before the source fix, the initial malformed-Type fixture admitted explicit Type streams as image rows. The final passing test below also covers duplicate Type keys.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects explicit non-XObject image stream Type values before image review
Expected: 2
Actual: 5
1 test files, 5 assertions, 1 failures
```

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectTypeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects explicit non-XObject image stream Type values before image review
1 test files, 63 assertions, 0 failures
```

Focused Image XObject current-base family:

```text
mapfile -t tests < <(rg --files lanes/markerpdf/tests | rg '/Pdf(ImageXObject|PageResourceImageXObject).*CurrentBaseTest\.php$' | sort); php tools/run-tests.php "${tests[@]}"
Focused test run: 39 selected test files (root lock skipped)
39 test files, 2831 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-type-boundary-currentbase.php
```

The smoke exits 0 and emits `explicit_type_rejected=true`, `rejected_hashes_excluded=true`, `typeless_image_reviewed=true`, `valid_type_image_reviewed=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Image XObject Subtype boundaries, top-level dimension boundaries, duplicate resource-name rejection, malformed `Do` or `cm` operand handling, Form XObject traversal, optional content, masks/SMask/alternates/metadata/OPI review, color-space Decode handling, resource-entry tail rejection, page clipping, pattern paints, Type3 CharProc images, encrypted fail-closed review, or generic raster decoding.

The bounded behavior is only the explicit stream dictionary `/Type` gate before Image XObject media review.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF dictionary scanner, top-level operand boundary helpers, exact object-name resolution, stream decoders, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
