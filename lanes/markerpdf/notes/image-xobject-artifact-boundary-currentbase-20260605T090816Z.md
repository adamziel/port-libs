# markerPDF Image XObject Artifact Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T090816Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T090816Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from rendered page/image output:

- `marker/pdf/extract_text.py` obtains text pages through `pdftext.extraction.dictionary_output()` and PDFium text pages.
- `marker/pdf/images.py` renders page imagery with PDFium/PIL and converts it to RGB outside the text pipeline.

Under the current no-GPU lane scope, the PHP port owns the native parser boundary before any future raster backend. PDF `/Artifact` marked-content wraps decorative/background content that should stay reviewable but should not count as imported semantic media for WordPress paragraph/media handoff.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now filters `/Artifact` marked-content blocks before counting Image XObject `Do` invocations:

- direct `/Artifact BMC ... EMC` image invocations are preserved as uninvoked review rows;
- direct `/Artifact << ... >> BDC ... EMC` image invocations are likewise unpainted for import-count purposes;
- normal non-artifact Image XObject invocations keep their CTM/bbox, decoded hash/length metadata, and payload-exclusion behavior;
- the artifact filter is scoped to the Image XObject invocation scanner and does not change general text extraction paths.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
FAIL keeps artifact-marked image XObject invocations as unpainted review metadata
Expected: 1
Actual: 3
1 test files, 606 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
PASS keeps artifact-marked image XObject invocations as unpainted review metadata
1 test files, 634 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-artifact-boundary-currentbase.php
image_xobject_count=3
invoked_image_xobject_count=1
uninvoked_image_xobject_count=2
decorative_artifact_invoked=false
background_artifact_invoked=false
content_image_invoked=true
artifact_payload_excluded_from_gutenberg_text=true
content_payload_excluded_from_gutenberg_text=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, page/Form resource inheritance, optional-content visibility, inline OCMD visibility, CTM placement, contents-array graphics-state preservation, clipping paths, page box clipping, rotated/UserUnit display geometry, exact generation ownership, SMask/Mask image stream metadata, alternates, metadata streams, named ColorSpace resources, top-level XObject dictionary parsing, ExtGState transparency review, JPX SMaskInData, inline image parsing, DCT/CCITT/JPX/JBIG2 preview filters, or Form-resource image discovery.

The bounded behavior here is specifically `/Artifact` marked-content suppression before Image XObject invocation counts for WordPress media import review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page resource resolver, content tokenizer, marked-content token filtering, stream decoders, image review rows, and WordPress smoke renderer. Full rendered pixel parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya, Texify, Torch, Streamlit/FastAPI model workers, Poppler, Ghostscript, and other external PDF tools were not run.
