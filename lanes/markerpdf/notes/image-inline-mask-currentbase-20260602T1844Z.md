# image-inline-mask-currentbase

## Source Truth

- Upstream: `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Source URL inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`
- Relevant upstream boundary: `marker/pdf/images.py::render_image()` renders a PDF page at `dpi / 72`, disables annotations, converts to RGB, and `render_bbox_image()` crops that RGB image. `marker/images/extract.py::extract_page_images()` then inserts image Markdown spans.

## Implemented

- Added `PdfImageRenderer::inlineImageMaskPreviewRows()` for inline `BI ... ID ... EI` image masks.
- Expands inline abbreviations, decodes native inline payload filters, unpacks packed stencil samples, applies `/ImageMask` `/Decode` opacity, and reports incomplete sample data without leaking payload bytes into visible text.
- Added a WordPress smoke example for inline ImageMask preview metadata.

## Focused Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php` failed with missing `PdfImageRenderer::inlineImageMaskPreviewRows()`.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`: pass.
- `php -l lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php`: pass.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-mask-preview-currentbase.php`: pass.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json`: pass.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`: 3 files, 521 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-mask-preview-currentbase.php`: emitted `visible_text_imported=true`, `excluded_inline_mask_payload_text=true`, four preview mask pixels, and no Python/PIL/external-tool execution.
- `git diff --check -- lanes/markerpdf`: pass.

## Non-overlap

This does not repeat accepted inline image payload exclusion, inline JBIG2/ImageMask review metadata, inline JPX soft-mask decode metadata, image `/Decode` stencil planning, ColorKey `/Mask` review, or soft-mask transfer slices. The new behavior decodes inline ImageMask payload samples into bounded opacity preview rows.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF value parser, inline abbreviation expansion, image filter decoder, packed-sample reader, and ImageMask `/Decode` opacity mapping. Full raster parity remains gated on pypdfium2/PIL or a future native raster backend.
