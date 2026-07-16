# markerPDF Image XObject Parent Form Generation Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T093414Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T093414Z`
Base accepted HEAD: `fc9cc5ac780ad879f0d013a4c9808a06a29c2d50`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image rendering and media handoff. The native PHP no-GPU path should therefore expose enough parser metadata for Image XObject review without treating image stream payloads as visible text.

PDF indirect references are generation-specific. When a page paints a Form XObject whose resources point to a nested Image XObject, the nested image review row already reports the nested image object generation. It also needs the parent Form XObject generation so downstream WordPress/media review can distinguish current Form resources from stale same-object-number generations.

References:

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/images.py` and image extraction flow.
- PDF object indirect-reference semantics and XObject/Form resource dictionaries in the PDF Reference.

## Behavior

`PdfTextExtractor::imageXObjectBoundaryEntriesForResourceOwner()` now threads the current parent Form XObject generation into nested Image XObject boundary rows. The emitted review entry includes:

- `parent_form_xobject_object`
- `parent_form_xobject_generation`
- generation-specific nested image object metadata
- inherited invocation matrices and bbox placement from the current Form generation

The focused fixture includes stale `5 0 R` Form and `6 0 R` Image objects plus current `5 1 R` Form and `6 1 R` Image objects. The page paints `/Generated Form 5 1 R`; the review row reports parent Form generation `1`, nested image generation `1`, and the current decoded payload hash while rejecting the stale generation hash.

Image payload stream bytes remain excluded from visible text and serialized review JSON.

## Red First

Before the source edit, the focused test failed because the nested review row did not expose the parent Form generation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "parent_form_xobject_generation" in lanes/markerpdf/tests/PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php on line 78
FAIL reports generation-specific parent Form XObject metadata for nested image review rows
Expected: 1
Actual: NULL
1 test files, 15 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reports generation-specific parent Form XObject metadata for nested image review rows
1 test files, 36 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectIndirectBBoxBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1454 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(ImageXObject|PageResourceImageXObject).*CurrentBaseTest\.php$' | sort)
Focused test run: 38 selected test files (root lock skipped)
38 test files, 2761 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-parent-form-generation-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=1`, `invoked_image_xobject_count=1`, `resource_path=["Generated Form","Nested Generated Image"]`, `parent_form_xobject_object=5`, `parent_form_xobject_generation=1`, `nested_image_object=6`, `nested_image_generation=1`, `nested_image_dimensions=[4,2]`, `current_generation_sha256_matches=true`, `stale_generation_rejected=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-parent-form-generation-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors and `git diff --check` passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `3020 -> 3021`.
- WordPress smoke scenarios: `2499 -> 2500`.
- New focused test: `PdfImageXObjectParentFormGenerationBoundaryCurrentBaseTest.php`, 1 PASS case / 36 assertions.
- Focused Image XObject current-base family after patch: `38 test files / 2761 assertions / 0 failures`.
- Mapped upstream denominator: unchanged; this is an additive current-base boundary inside the existing Image XObject parser/review area.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, exact image object generation, resource entry tail rejection, duplicate resource names, Form alias suppression, Form BBox/Matrix boundaries, pattern generation/provenance, optional content, OCG/OCMD visibility, ExtGState transparency, path/rect clipping, repeated moveto clipping, malformed `Do`/`cm`, filter/ColorSpace/Decode/mask/SMask metadata, Type3 CharProc image review, inline image boundaries, page-box rotation/UserUnit display geometry, encrypted fail-closed image review, or PageLabels/xref/annotation/form/metadata clusters.

The bounded behavior is only generation-specific parent Form XObject provenance on nested Image XObject review rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, exact-generation object lookup, content tokenizer, Form XObject resource traversal, matrix/bbox review helpers, stream decoder, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
