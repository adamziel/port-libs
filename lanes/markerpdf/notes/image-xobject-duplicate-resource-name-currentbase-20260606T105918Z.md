# markerPDF Image XObject Duplicate Resource Name Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260606T105918Z`

Base accepted HEAD: `acaa655f41a326695b1b8edaa14a30da83e3ddae`

## Behavior

Pinned upstream markerPDF source remains `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU PHP lane, searchable PDF text and media review are native parser boundaries before any raster/OCR/model handoff.

PDF resource dictionaries are the source of truth for `/Name Do` Image XObject paints. When the `/XObject` resource category declares the same decoded resource name more than once, the name is ambiguous. `PdfTextExtractor::extractImageXObjectBoundaryReview()` now rejects duplicated `/XObject` resource names before placement review while preserving unique sibling image resources. This prevents WordPress media review from trusting a last-key-wins image payload or hash for an ambiguous resource name, and it keeps all raster payload bytes out of visible paragraphs.

## Evidence

Red-first after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php`

Result: `1 test files / 4 assertions / 1 failure`; the duplicate `/Dup Image` resource was reported as an invoked image row.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php`

Result: `1 test files / 29 assertions / 0 failures`.

Adjacent Image XObject family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`

Result: `12 test files / 1561 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-image-xobject-duplicate-resource-name-currentbase.php`

Result: emitted `duplicate_resource_name_rejected=true`, `unique_sibling_image_reviewed=true`, `duplicate_hashes_excluded=true`, `payload_in_visible_text=false`, and two Gutenberg paragraphs for the visible searchable PDF text. It executed no Python, models, OCR, PDFium, PIL, Poppler, Ghostscript, or external PDF tools.

Syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageXObjectDuplicateResourceNameBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-duplicate-resource-name-currentbase.php` => no syntax errors.
- `git diff --check -- lanes/markerpdf` => no output, exit 0.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary tokenizer, resource reference scanner, exact-generation object resolver, content-stream tokenizer, stream decoder, Image XObject review rows, and WordPress smoke renderer. Full raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, direct placement, Form XObject traversal, resource-entry wrappers, exact object generations, optional content, artifact suppression, malformed `Do` or `cm` operands, text-object/compatibility boundaries, page clipping, q/Q current-path handling, ExtGState transparency, ImageMask paint colors, masks/SMask/alternates/metadata/OPI, color-space Decode and filter boundaries, Type3 CharProc image review, tiling/stroking pattern image traversal, duplicate top-level page `/Resources`, or malformed indirect `/XObject` category object tails. The bounded behavior is only duplicate decoded resource names inside the selected `/XObject` resource category before Image XObject placement review.
