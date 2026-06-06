# markerPDF Image XObject OCMD Visibility Expression Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T153552Z`

## Source Truth

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable PDF text extraction from image rendering. Under the current no-GPU scope, the PHP lane owns native parser boundaries that decide whether an Image XObject would be painted by the default-view PDF graphics state before any future raster handoff.

PDF optional-content membership dictionaries can use `/VE` visibility expressions. `/VE` is stronger than the simple `/P` membership policy for deciding visibility. The native review path now evaluates `/And`, `/Or`, and `/Not` expression arrays with generation-exact OCG references before falling back to the existing `/P` policy for malformed or unsupported expressions.

## Behavior

`PdfTextExtractor::optionalContentMembershipVisible()` now evaluates `/VE` for OCMD dictionaries. Image XObjects with `/OC << /Type /OCMD /OCGs [...] /P /AnyOn /VE [/And visible hidden] >>` stay reviewable but unpainted even though `/P /AnyOn` alone would make them visible. Nested `/Not` expressions such as `[/And visible [/Not hidden]]` still count visible invocations.

The implementation reuses the existing optional-content state map, exact-reference object lookup, content-stream filtering, and image XObject review metadata. Malformed `/VE` expressions deliberately fall back to the prior `/P` policy instead of adding a new fail-closed behavior.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL honors OCMD visibility expressions before counting image XObject invocations
Expected: false
Actual: true
1 test files, 1245 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
1 test files, 1260 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php
image_xobject_count=6
invoked_image_xobject_count=3
uninvoked_image_xobject_count=3
ve_hidden_optional_content_visible=false
ve_hidden_invoked=false
ve_visible_optional_content_visible=true
ve_visible_invoked=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted image payload exclusion, CTM placement, Form XObject traversal, OCG `/ON`/`/OFF` generation handling, inline OCMD `/P` policy handling, optional-content page-stream filtering, artifact suppression, clipping paths, page box clipping, rotation/UserUnit display geometry, SMask/Mask metadata, ColorKey masks, named ColorSpace resources, ExtGState transparency, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline-image tokenizer boundaries, malformed `Do` operand rejection, pattern image paints, or encrypted fail-closed review.

The bounded behavior is only OCMD `/VE` visibility-expression evaluation before Image XObject invocation counting and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, optional-content visibility model, content tokenizer, Image XObject review extractor, stream decoders, and WordPress smoke path. Live OCR, PDFium rasterization, PIL image conversion, Surya/Torch model execution, Texify, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
