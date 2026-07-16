# markerPDF Image XObject Top-Level Resource Boundary

Session: `port-dev-markerpdf-image-xobject-20260605T031446Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T031446Z`
Base accepted HEAD: `f94cbdd4376265f512a55fce6d52a7d1fcd0e4c1`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` separates searchable page text extraction from page/image rendering. The native no-GPU PHP boundary keeps raster image resources as review metadata while visible WordPress paragraphs come only from page text/Form text that is reachable through valid page resources.

Relevant upstream source-truth files remain:

- `marker/pdf/extract_text.py`
- `marker/pdf/images.py`
- `marker/images/extract.py`

## Behavior

The native XObject resource lookup now walks only top-level entries in the `/XObject` resource dictionary. Nested private dictionaries such as `/XObject << /Hero 5 0 R /Private << /PrivateImage 6 0 R /PrivateForm 7 0 R >> >>` are skipped before both image review and Form XObject text expansion.

Before this patch, a red probe reported:

```json
{
  "image_xobject_count": 2,
  "invoked_image_xobject_count": 2,
  "resource_names": ["Hero Image", "Private Image"]
}
```

After the patch, only the top-level `Hero Image` resource is callable review metadata. The nested private image is not counted, and a nested private Form XObject invoked by name does not leak `Private Form Text Leak` into WordPress paragraphs.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`
  - `1 test files, 346 assertions, 0 failures`
  - Focused image XObject test grew from `327` to `346` assertions.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php`
  - `1 test files, 13 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php`
  - `1 test files, 75 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-top-level-resource-boundary-currentbase.php`
  - Emits `top_level_resource_selected=true`, `nested_private_image_rejected=true`, `nested_private_form_text_rejected=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, clipping, optional-content, exact-generation, SMask, Mask, metadata stream, alternates, ColorKey, DCT/CCITT/JPX/JBIG2, inline-image, or Form-resource image review behavior. The new behavior is specifically top-level `/XObject` resource category parsing, so nested private resource dictionaries do not become callable XObject resources.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF token/dictionary parser and existing image/Form XObject review path. It does not execute Python, models, PDFium, PIL, Poppler, Ghostscript, live OCR, or external PDF tools.
