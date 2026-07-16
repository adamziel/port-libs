# markerPDF Image XObject Pattern Wrapper Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260606T034911Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T034911Z`
Base accepted HEAD: `36f4b82297cc95c9287bf2f8b349828229580692`

## Source Truth

Upstream markerPDF separates searchable text extraction from image rendering: text pages stay in the PDF text pipeline while images are handed to `marker.pdf.images.render_image`/PDFium-style raster paths. Under the current no-GPU scope, this native PHP slice maps the parser boundary before that image handoff.

PDF resource dictionary values are indirect objects. A resource entry may resolve through an exact indirect-reference wrapper before reaching the actual stream object, and resource-cycle wrappers must fail closed. The existing PHP XObject resource path already handled this for page/form Image XObjects; tiling Pattern resources needed the same exact-generation wrapper resolution before traversing pattern streams for Image XObject review.

## Behavior

`PdfTextExtractor` now uses one exact resource wrapper resolver for both XObject and Pattern resource entries:

- `/Pattern << /Wrapped Tile 12 0 R >>` where `12 0 obj` contains `11 0 R` now resolves to the actual tiling pattern stream `11 0 R`.
- Image XObjects painted inside the resolved pattern stream are recorded with pattern provenance, placement bboxes, decoded stream hashes, and review-only payload handling.
- Cyclic pattern wrappers such as `13 0 obj 13 0 R endobj` resolve to `null` and are skipped instead of triggering an unbounded walk.

The fixture paints `/Wrapped Tile` into a page path. The resolved pattern stream paints `/Tile Image` and also defines an unused image resource. WordPress-visible text remains only the page text; image payload bytes stay out of paragraphs and serialized review JSON.

## Red First

Before the source edit, the focused test failed because pattern resources stopped at the wrapper object:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves wrapped tiling pattern resources before Image XObject boundary review (lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php)
Values are not identical
Expected: 2
Actual: 0

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves wrapped tiling pattern resources before Image XObject boundary review

1 test files, 38 assertions, 0 failures
```

Focused Image XObject family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(ImageXObject|PageResourceImageXObject).*CurrentBaseTest\.php$' | sort)
Focused test run: 9 selected test files (root lock skipped)
9 test files, 1363 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-wrapper-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=2`, `invoked_image_xobject_count=1`, `uninvoked_image_xobject_count=1`, `wrapped_pattern_resolved=true`, `wrapped_pattern_payload_hash_matches=true`, `unused_wrapped_pattern_reviewed=true`, `cycle_pattern_wrapper_skipped=true`, `payload_in_visible_text=false`, `payload_in_review_json=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectPatternWrapperCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-pattern-wrapper-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks reported no syntax errors and `git diff --check` passed.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2370 -> 2371`.
- WordPress smoke scenarios: `2030 -> 2031`.
- New focused assertions: `38`.
- Focused Image XObject family after patch: `9 test files / 1363 assertions / 0 failures`.
- Mapped upstream denominator: unchanged; this is an additive current-base boundary inside the existing Image XObject parser area.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, direct XObject resource wrappers, Form XObject image resources, page resource inheritance, exact image auxiliary object generations, optional content, compatibility/text-object exclusion, malformed `Do`/`cm`, masks/SMask/alternates/metadata/OPI, color-space Decode and preview-only filters, page clipping, q/Q current-path handling, ExtGState transparency, artifact/marked-content metadata, direct tiling/stroking pattern streams, Type3 CharProc images, or encrypted fail-closed image review.

The bounded behavior is only exact indirect-reference wrapper resolution for Pattern resources before traversing a tiling pattern stream for Image XObject review.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, exact-generation object lookup, content tokenizer, tiling-pattern stream decoder, matrix/clip review helpers, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
