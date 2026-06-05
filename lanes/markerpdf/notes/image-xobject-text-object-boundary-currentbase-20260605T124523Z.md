# markerPDF Image XObject Text-Object Boundary Current Base

## Scope

This isolated markerPDF slice maps a native PDF image XObject boundary: `/Do`
operators found while a content stream is inside a text object (`BT` ... `ET`)
are not painted image invocations. A later valid graphics-state `/Do` still
records placement metadata for WordPress media review.

The upstream behavior mapped here is the no-GPU boundary between pdftext-style
searchable text extraction and `marker.pdf.images.render_image` image handoff:
image payloads stay out of visible text, and image review metadata records only
actual painted XObject invocations.

## Red-First Evidence

After adding the focused case, the current base counted the fake text-object
image as painted:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL keeps image XObject Do operators inside text objects unpainted
Expected: 1
Actual: 2
1 test files, 734 assertions, 1 failures
```

## Implementation

`PdfTextExtractor::contentXObjectInvocationDetails()` now tracks `BT`/`ET` and
clears operands while inside text objects. Graphics-state operators, including
`Do`, `cm`, clipping, and `gs`, are ignored in that invalid text-object scope
for image invocation review, while normal graphics-state image calls remain
unchanged.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 755 assertions, 0 failures
```

This adds 1 focused PASS case and 23 assertions to the image XObject boundary
file.

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-text-object-boundary-currentbase.php
```

The smoke exits 0 and emits metadata with:

```text
text_object_do_unpainted=true
painted_sibling_invoked=true
payload_in_visible_text=false
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is required. This reuses the native PHP content-stream
tokenizer, PDF object parser, stream filter decoder, and image XObject review
metadata path. GPU/model execution, raster rendering, Python, and external PDF
tools remain intentionally out of scope for this lane.

## Non-Overlap

This does not repeat accepted image XObject placement, Form XObject matrix,
Contents-array graphics-state preservation, clipping, optional content,
artifact-marked image suppression, ExtGState transparency, page geometry,
generation-exact auxiliary streams, color-space/mask/decode metadata, or the
existing malformed extra-operand `/Do` boundary. The new behavior is only the
`BT`/`ET` text-object operator boundary for image XObject invocation review.
