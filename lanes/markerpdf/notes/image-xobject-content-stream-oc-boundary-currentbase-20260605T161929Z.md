# markerPDF Image XObject Content Stream Optional-Content Boundary

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable page text extraction separate from PDF image rendering/review. In native no-GPU scope, page content stream visibility must be resolved before image `/Do` operators are counted as painted Image XObject invocations.

PDF optional content can be attached directly to a content stream object through `/OC`. A hidden content stream must not contribute visible text and must not count its image XObject `Do` operators as painted. The Image XObject resource itself remains review-only metadata so WordPress can audit the hidden media object without serializing raster payload bytes.

## Implementation

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now passes catalog-derived optional-content states into page content stream decoding.
- `pageDecodedContentStreams()` skips page content stream objects whose own `/OC` resolves hidden before `contentXObjectInvocationDetails()` scans `Do` operators.
- The existing image resource review path still lists the hidden stream's Image XObject resource as decoded metadata with `invoked=false`, `invocation_count=0`, and no raw payload serialized into text or JSON review output.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL skips hidden optional-content page streams before counting image XObject invocations
Expected: 1
Actual: 2
1 test files, 905 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
PASS skips hidden optional-content page streams before counting image XObject invocations
1 test files, 926 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-content-stream-oc-currentbase.php
```

The smoke emits `content_stream_oc_hidden_invocation_excluded=true`, `content_stream_oc_hidden_metadata_retained=true`, `visible_content_stream_invocation_counted=true`, `hidden_text_excluded=true`, payload-exclusion flags true, and model/external-tool execution flags false.

Syntax and handoff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-content-stream-oc-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-content-stream-oc-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
0 failures
```

## Non-Overlap

This does not repeat accepted image XObject resource inheritance, Form XObject traversal, image object-level `/OC`, marked-content `/OC`, inline OCMD dictionaries, OCG generation matching, CTM placement, clipping paths, ExtGState transparency, page geometry, artifact suppression, malformed `Do` operand rejection, text-object/compatibility boundaries, pattern image paints, image mask/stencil colors, or encrypted fail-closed review. The bounded behavior is specifically page content stream object-level `/OC` filtering before Image XObject invocation counting.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, page content stream resolver, optional-content visibility helpers, Image XObject resource review path, content-stream tokenizer, and focused PHP runner. Live OCR, pypdfium/PDFium raster rendering, PIL image conversion, Surya/Torch models, Texify, Streamlit/FastAPI workers, benchmark/model downloads, and external OCR/rendering helpers remain intentionally out of scope under the no-GPU markerPDF directive.
