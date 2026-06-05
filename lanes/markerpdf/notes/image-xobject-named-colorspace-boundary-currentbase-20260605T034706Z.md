# markerPDF Image XObject Named ColorSpace Boundary Current Base

Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T034706Z`

Source-truth boundary:

- Upstream `sddai/markerPDF` is pinned in the manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream visible text comes from `marker/pdf/extract_text.py` through pdftext/PDFium text pages, while images are rendered through `marker/pdf/images.py::render_image()` and converted to RGB (`https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`). This native no-GPU boundary therefore keeps Image XObject bytes out of visible WordPress paragraphs while preserving review metadata needed before a future raster backend.
- PDF image dictionaries may use named `/ColorSpace` resources from the active page or Form XObject resource dictionary. The native review path must resolve those names before color-key mask component validation; otherwise named RGB/CMYK images look component-unknown even though the active resource dictionary supplies the family.

Implementation:

- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now passes the active resource owner into image review rows.
- Image review rows resolve `/ColorSpace /Name` and inline `/CS /Name` through `Resources.ColorSpace`, including Form XObject resources, and expose `color_space_resource_name`, `color_space_resolved_from_resources`, and `color_space_resource_source`.
- Indirect color-space resource values are resolved by exact object generation so stale same-number color-space definitions do not replace the selected resource.
- Color-key mask review now receives the resolved family, so RGB masks validate against 3 components and CMYK masks validate against 4 components before WordPress media review.

Red-first evidence:

Before the fix, the focused named-color-space fixture failed in `PdfImageXObjectBoundaryCurrentBaseTest.php` because the page image entry reported `color_space = null` and the color-key mask had `expected_components = null` for `/ColorSpace /CSHero`.

Focused verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php`: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php`: 1 test file / 376 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-boundary-currentbase.php`: exits 0 and emits `first_color_space=DeviceRGB`, `first_color_space_resource_name=CSHero`, and resource-resolution metadata while keeping image, mask, alternate, metadata, and optional-content payload bytes out of paragraphs.

Non-overlap:

This does not repeat accepted image XObject payload exclusion, CTM placement, Form XObject matrix/BBox clipping, optional-content-hidden image review, soft-mask/mask/alternate/metadata exact-generation review, ColorKey raw sample review, inline image tokenizer/filter boundaries, or image renderer RGB preview planning. The new behavior is specifically named `/ColorSpace` resource resolution for Image XObject boundary review and color-key mask component validation.

Dependency closure:

No new support component is needed. The slice reuses the native PDF object scanner, exact-reference lookup, resource dictionary parser, Image XObject review path, Form XObject resource stack, stream filters, color-key mask review, and WordPress smoke. Full upstream markerPDF parity remains intentionally out of no-GPU scope where it requires live pdftext/PDFium execution, pypdfium/PIL rendering, Surya/Texify/Torch models, tabled-pdf model inference, Streamlit/FastAPI workers, benchmark downloads, or OCR/model execution.
