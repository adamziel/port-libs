# markerPDF page resource image XObject inheritance current-base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T083457Z`
Session: `port-dev-markerpdf-resource-inherit-20260605T083457Z`
Base accepted HEAD: `3fbce78dff945c4108221de18bd13fb2feb4f8f0`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through native PDF extraction before OCR/layout/model work, and image handling is a bounded review/media path rather than a text source. PDF page `/Resources` is inheritable through the page tree, but a valid leaf `/Resources` dictionary is authoritative and is not partial-merged with parent resource categories. This slice keeps that native parser boundary in the PHP port without Python, OCR, Surya, Texify, Torch, or external PDF tooling.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records the resolved page resource owner for top-level page image XObject review rows. When a page inherits `/Resources` from an ancestor `/Pages` node, image review entries expose `page_resource_inherited=true`, `page_resource_owner_object`, `page_resource_object`, and `page_resource_generation`. A page with its own valid local `/Resources` dictionary still blocks parent fallback, so an image XObject referenced only by the parent dictionary is not backfilled onto that leaf page.

Nested Form XObject image review keeps the existing resource boundary: page resource provenance is propagated only when a resource-less form inherits the invoking page resources, and it is cleared when the form has its own resource dictionary.

## Verification

Red-first before implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php
```

Failed with 1 selected test file, 11 assertions, 1 failure because `page_resource_inherited` was missing from the image review row.

Passing after implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php
```

Passed: 1 test file, 25 assertions, 0 failures.

Focused page-resource/image family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEscapedKidsInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
```

Passed: 8 test files, 817 assertions, 0 failures.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-resource-image-xobject-inheritance-currentbase.php
```

Passed with `inherited_image_resource_reported=true`, `leaf_resource_override_blocks_parent_image=true`, `image_payload_excluded_from_gutenberg_text=true`, `review_only_image_count=1`, `visible_paragraph_count=2`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-image-xobject-inheritance-currentbase.php
git diff --check -- lanes/markerpdf
```

PHP lint passed for all changed PHP files. `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted font ToUnicode/width resource inheritance, malformed/stream/category resource blocking, escaped Kids inheritance, generation-specific resource references, Form XObject resource inheritance, image filter/SMask/color-space review, optional-content image invocation review, or payload exclusion. The new behavior is specifically observable page-resource owner provenance on inherited image XObject review rows plus the leaf local `/Resources` no-backfill boundary for image review.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, page-tree lineage walker, page resource resolver, Image XObject boundary review, stream decoder, page property extractor, and WordPress smoke path. Full upstream model/OCR parity remains intentionally out of scope under the no-GPU markerPDF directive.
