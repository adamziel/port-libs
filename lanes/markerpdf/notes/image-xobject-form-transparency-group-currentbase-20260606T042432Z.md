# markerPDF Image XObject Form Transparency Group Current Base

Session: `port-dev-markerpdf-image-xobject-20260606T042432Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T042432Z`
Base accepted HEAD: `4a0751b651196808f8b4b7d8301ab959dd72f86d`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image raster/media handoff. PDF Form XObjects may declare a `/Group << /S /Transparency ... >>` dictionary that defines compositing context for everything painted inside the form, including nested Image XObjects. Under the current no-GPU scope, this native PHP slice records that context as review-only metadata before any future raster backend executes.

## Behavior

`PdfTextExtractor` now carries a Form transparency-group stack through nested Image XObject invocation review:

- Direct or indirect Form `/Group` dictionaries are parsed without executing actions, raster tools, OCR, or models.
- The group subtype, resolved color-space family/resource metadata, `/I` isolation flag, and `/K` knockout flag are attached to nested image review rows.
- Image payload bytes remain excluded from visible WordPress text and from serialized review metadata.

The focused fixture invokes `/Grouped Logo`, a Form XObject with `/Group << /S /Transparency /CS /DeviceRGB /I true /K false >>`, whose stream paints `/Nested Group Image`. The resulting image row reports `form_transparency_group_count=1` and keeps visible text limited to the page text.

## Verification

Syntax checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-transparency-group-currentbase.php
```

All reported no syntax errors.

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS records Form XObject transparency groups before nested image handoff
...
1 test files, 1131 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-transparency-group-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=1`, `invoked_image_xobject_count=1`, `resource_path=[Grouped Logo,Nested Group Image]`, `form_transparency_group_count=1`, `form_transparency_group_subtype=Transparency`, `form_transparency_group_color_space=DeviceRGB`, `form_transparency_group_isolated=true`, `form_transparency_group_knockout=false`, `form_transparency_group_review_only=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Whitespace:

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2387 -> 2388`.
- WordPress smoke scenarios: `2041 -> 2042`.
- Focused Image XObject test after patch: `1 test files / 1131 assertions / 0 failures`.
- Mapped upstream denominator: +1 current-base native Image XObject/Form transparency-group behavior.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, Form XObject resource traversal, page resource inheritance, Form `/Matrix` placement, clipping, q/Q current-path behavior, optional content, marked-content metadata, ExtGState alpha/soft-mask review, image SMask/Mask/Decode/filter metadata, tiling/stroking pattern Image XObject traversal, exact-generation auxiliary streams, or encrypted fail-closed image review.

The bounded behavior is only Form XObject `/Group` transparency compositing context propagated to nested Image XObject review rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF dictionary parser, exact-generation object lookup, content tokenizer, Form XObject recursion, matrix/clip helpers, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
