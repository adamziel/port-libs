# DCTDecode Post-Filter Boundary Current Base

## Source Truth

Upstream markerPDF routes image raster handling through `marker.pdf.images.render_image`. Under the current no-GPU markerPDF scope this port keeps DCT/JPEG image bytes review-only, preserves searchable PDF text, and exposes image/filter handoff metadata without invoking OCR, PDFium, PIL, Poppler, Ghostscript, or model workers.

## Red-First Gap

The existing DCTDecode current-base coverage already guarded direct JPEG SOI/EOI ownership, stale `/Length`, inline image, native-prefix filter, indirect-filter, and stream terminator boundaries. A remaining native metadata gap was a declared filter stack where `DCTDecode` is followed by additional filters, for example `/Filter [/DCTDecode /ASCIIHexDecode /JPXDecode]`. The generic filter metadata preserved those names, but there was no DCT-specific boundary describing that post-DCT stages are unreachable for native raster decode after preview-only JPEG data.

## Implementation

- `PdfImageRenderer::imageColorSpaceSoftMaskPlan()` now emits `dctdecode_filter_boundary` alongside the existing image and CCITT boundary metadata.
- `PdfTextExtractor::extractImageXObjectBoundaryReview()` now emits the same `dctdecode_filter_boundary` on Image XObject review rows, and nested mask/alternate image reviews reuse the same helper shape.
- The boundary records the declared DCT alias, native filters before/after DCT, preview-only filters before/after DCT, terminal/post-DCT status, and `post_dctdecode_filters_block_native_decode=true` when any filters follow the DCT stage.
- Renderer notes add `dctdecode_post_filters_block_native_decode` for WordPress/media-review callers.

## Focused Evidence

- `php -l lanes/markerpdf/src/PdfImageRenderer.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`  
  Result: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-post-filter-boundary-currentbase.php`  
  Result: no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`  
  Result: `1 test files, 501 assertions, 0 failures`; new PASS case: `marks filters declared after DCTDecode as unreachable native image stages`.
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-post-filter-boundary-currentbase.php`  
  Result: emitted two Gutenberg paragraphs and metadata with `post_dctdecode_filters_block_native_decode=true`, `xobject_boundary_matches_renderer=true`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not alter the earlier DCTDecode direct stream-length, missing-Length, prefix Flate/LZW/RunLength/ASCIIHex, indirect-filter, inline-image, Crypt Identity, or stream terminator ownership cases. It only adds review metadata for filters declared after the preview-only DCT/JPEG stage.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF dictionary/filter parsing, image filter metadata, and review-only image boundary path. GPU/model execution, live OCR, Surya/Texify/Torch, PDFium, PIL, Poppler, Ghostscript, and external PDF tools remain intentionally out of scope.
