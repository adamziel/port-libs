# markerPDF Image XObject compatibility-section boundary

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T135806Z`

Base accepted HEAD: `7c27a6118223c3a795b10dae9f12e2e6310f566a`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable page text extraction separate from image rendering: PDF text comes through `marker/pdf/extract_text.py::get_text_blocks()` / `naive_get_text()`, while image payloads are rendered through `marker/pdf/images.py::render_image`.
- PDF content streams use `BX` / `EX` compatibility sections to bracket unknown or compatibility-only content. Native media review should not treat image `/Do` operators inside those sections as painted image invocations.
- WordPress import needs the image resource listed for review, but compatibility-section raster payload bytes must not become paragraphs or inflate painted media placement counts.

## Behavior

`PdfTextExtractor::contentXObjectInvocationDetails()` now tracks compatibility-section depth while scanning image XObject invocations. Outside text objects:

- `BX` increments compatibility depth.
- Operators and operands inside compatibility sections are ignored for image invocation review.
- `EX` decrements compatibility depth.
- Later normal `/Do` calls still preserve CTM-derived image bbox metadata.

This prevents compatibility-section image resources from being counted as painted media while keeping them as uninvoked, review-only Image XObject rows.

## Evidence

Focused Image XObject boundary test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores image XObject Do operators inside compatibility sections
1 test files, 809 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-compatibility-boundary-currentbase.php
```

The smoke emits:

```text
compatibility_section_image_unpainted=true
painted_image_after_compatibility_section_counted=true
image_payloads_excluded_from_text=true
image_payloads_excluded_from_review_json=true
visible_paragraphs_preserved=true
executes_python_or_models=false
executes_external_pdf_tools=false
executes_pypdfium_or_pil=false
```

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1890 -> 1891` from the new focused PASS case.
- Focused assertion coverage for the Image XObject boundary file is now 809 assertions.
- WordPress scenario count moves `1711 -> 1712` from the added smoke.

## Non-Overlap

This does not repeat accepted Image XObject resource inheritance, Form XObject placement, optional content visibility, CTM placement, indirect Form matrices, graphics-state preservation across page contents, clipping paths, stream metadata, alternate streams, soft masks, ColorKey masks, Decode arrays, exact-generation auxiliary streams, color-space resources, nested private resources, ExtGState transparency, page-box clipping, JPX SMaskInData, rotated UserUnit display geometry, artifact marked-content image review, malformed `/Do` operand rejection, text-object `/Do` boundaries, encrypted fail-closed image review, or attachment/annotation metadata slices.

The bounded behavior is specifically `BX` / `EX` compatibility-section gating for image XObject invocation counts.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page content stream decoder, content tokenizer, Image XObject resource review path, CTM/clip placement review, and WordPress smoke renderer. Full upstream live OCR, pypdfium2/PDFium raster rendering, PIL image conversion, Surya/Torch models, Texify, table model execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
