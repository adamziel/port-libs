# markerPDF Image XObject Form Resource Provenance Boundary

Session: `port-dev-markerpdf-image-xobject-20260606T023024Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T023024Z`
Base: `8939543119a291af01b67d59e9e9d7db95241345`

## Source Truth

Upstream markerPDF separates searchable text extraction from image rendering: text comes from PDF text-page extraction, while image XObjects are rendered through the image handoff path. Under the current no-GPU/no-model scope, this native PHP slice keeps raster payloads review-only while preserving placement/provenance metadata needed by WordPress import review.

This slice covers a current-base image XObject boundary not covered by the accepted CTM/Form placement work: image XObjects painted from a Form XObject that has its own resource dictionary still belong to a page placement. The review row should therefore retain the page resource owner/object metadata that made the Form resource reachable.

## Behavior

Before this patch, a red probe for a nested Form-painted image returned:

- `page_resource_review_only=false`
- `page_resource_owner_object=null`

after resolving the nested image row. Placement and payload exclusion were correct, but page resource provenance was lost because recursion passed `null` whenever the Form had its own `/Resources`.

After this patch, `PdfTextExtractor::imageXObjectBoundaryEntriesForResourceOwner()` carries the page resource review metadata through Form XObject recursion while still using the Form resource dictionary for nested image lookup, ColorSpace review, and payload metadata.

The focused fixture verifies:

- one image XObject row for `["Logo Form", "Nested Logo"]`;
- nested Form placement matrix and bbox remain unchanged;
- `page_resource_inherited=true`;
- `page_resource_owner_object=2`;
- `page_resource_object=10`;
- `page_resource_generation=0`;
- `page_resource_review_only=true`;
- compressed image payload bytes remain excluded from visible WordPress text and review JSON.

## WordPress Smoke

`examples/wordpress-pdf-image-xobject-form-resource-provenance-currentbase.php` builds the same import boundary and emits:

- `nested_form_resource_path=["Logo Form","Nested Logo"]`
- `page_resource_inherited=true`
- `page_resource_owner_object=2`
- `page_resource_object=10`
- `page_resource_review_only=true`
- `payload_in_visible_text=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

The rendered paragraphs contain only the searchable intro/outro text.

## Verification

Red-first probe before source edit:

```text
php -r '... extractImageXObjectBoundaryReview(...) ...'
false
NULL
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves page resource provenance for images painted from Form XObject resources
1 test files, 40 assertions, 0 failures
```

Adjacent image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectEntryWrapperBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php
6 test files, 1250 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-resource-provenance-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-resource-provenance-currentbase.php
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted image payload exclusion, direct page image resource review, CTM operand rejection, Form XObject /Matrix placement, Type3 CharProc images, optional-content visibility, masks/soft masks, alternates, tiling patterns, clipping, DCT/CCITT/JPX/JBIG2 filter boundaries, inline-image review, or live raster/model/OCR execution.

The bounded behavior is specifically page resource provenance on Form-painted image XObject rows when the Form has its own `/Resources`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, page resource inheritance review, Form XObject recursion, image XObject review rows, Flate stream decoding, content stream parser, and WordPress smoke renderer. Full raster rendering via PDFium/PIL, OCR/model execution, Surya/Torch/Texify parity, and upstream GPU benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
