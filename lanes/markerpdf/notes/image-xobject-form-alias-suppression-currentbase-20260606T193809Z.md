# markerPDF Image XObject Form Alias Suppression Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T193809Z`

Base accepted HEAD: `29e4f7bdda7c79644e6c2fd45009285d82e10a2f`

## Behavior

Pinned upstream markerPDF source remains `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU PHP lane, searchable PDF text and image review are native parser boundaries before raster/OCR/model handoff.

PDF `/XObject` resources may bind the same Image XObject stream under different names at different resource scopes. `PdfTextExtractor::extractImageXObjectBoundaryReview()` already suppresses top-level uninvoked page image resources when that same image is painted through a nested Form XObject on the same page, but the suppression key still included the resource name. That left a stale uninvoked page-scope row when the page resource used `/Page Alias 6 0 R` and the Form resource painted `/Nested Alias 6 0 R`.

This slice keys that suppression by page index plus exact object number and generation instead of resource alias. The invoked nested Form XObject row remains authoritative, preserves the full resource path and placement bbox, and the same-page uninvoked page alias is not emitted to WordPress media review. Image payload bytes remain excluded from visible text and review JSON.

## Evidence

Red-first after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php`

Result: `1 test files / 4 assertions / 1 failure`; the review reported `image_xobject_count=2` because the uninvoked page alias was still present.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php`

Result: `1 test files / 29 assertions / 0 failures`.

Focused Image XObject family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`

Result: `15 test files / 1745 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-alias-suppression-currentbase.php`

Result: emitted `page_alias_suppressed=true`, `nested_resource_path=["Logo Form","Nested Alias"]`, `nested_parent_form_xobject_object=5`, `nested_bbox=[120,726,552,834]`, `payload_in_visible_text=false`, `payload_in_review_json=false`, and two Gutenberg paragraphs for the visible searchable PDF text. It executed no Python, models, OCR, PDFium, PIL, Poppler, Ghostscript, or external PDF tools.

Syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageXObjectFormAliasSuppressionCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-form-alias-suppression-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => no output, exit 0.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF resource scanner, exact-generation object resolver, content-stream tokenizer, Form XObject traversal, stream decoder, Image XObject review rows, and WordPress smoke renderer. Full raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, duplicate resource-name rejection, direct placement CTM review, Form XObject traversal/provenance, Form subtype-name decoding, resource-entry wrappers, exact object generations, optional content, artifact suppression, malformed `Do` or `cm` operands, text-object/compatibility boundaries, page clipping, q/Q current-path handling, ExtGState transparency, ImageMask paint colors, masks/SMask/alternates/metadata/OPI, color-space Decode/filter boundaries, Type3 CharProc image review, tiling/stroking pattern image traversal, duplicate top-level page `/Resources`, or malformed indirect `/XObject` category object tails. The bounded behavior is only suppressing a same-page top-level uninvoked page alias when the exact same image object/generation is invoked through a nested Form XObject under a different resource alias.
