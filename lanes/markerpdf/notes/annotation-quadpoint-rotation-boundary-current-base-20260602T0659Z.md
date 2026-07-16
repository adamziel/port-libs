# markerPDF Annotation QuadPoint Rotation Boundary

Slice: `markerpdf-annotation-quadpoint-rotation-boundary-current-base-20260602T0655Z`

Base accepted HEAD: `7607a3a6ceab309a4d912b6bf566aaa45d482f64`

## Source Truth

- Upstream markerPDF at pinned `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps pdftext span and line `bbox` values as the geometry used by `Page`/`Span` objects, and swaps page width/height when `page["rotation"]` is `90` or `270` in `marker/pdf/extract_text.py::pdftext_format_to_blocks`.
- The locked pdftext dependency source in `/tmp/markerpdf-pdftext-src/pdftext-0.3.18/pdftext/pdf/chars.py` reads `page.get_rotation()` and converts PDFium character boxes through `page_bbox_to_device_bbox()` before dictionary output. `/tmp/markerpdf-pdftext-src/pdftext-0.3.18/pdftext/pdf/utils.py` performs the page/device rotation and normalized bbox conversion.
- PDF text-markup annotations keep `/QuadPoints` in page user space, so native review markup needs a bounded conversion into marker/pdftext display-space rectangles before intersecting supplied spans on rotated pages.

## Implementation

- `PdfMarkupAnnotationExtractor` now derives page geometry for text-markup annotations from inherited `/MediaBox`, `/CropBox`, and `/Rotate`.
- Extracted markup rows keep existing raw PDF page-space `quad_rects` and now also include `pdftext_quad_rects`, `pdftext_rect`, `page_bbox`, `page_rotation`, and `display_page_bbox`.
- `applyMarkupsToPages()` uses transformed `marker_pdftext_display` QuadPoints only when the supplied page carries marker/pdftext-style `bbox` and `rotation` matching the PDF page geometry. Legacy supplied pages without page-level geometry continue to use raw page-space `quad_rects`.

## WordPress Smoke

Added `examples/wordpress-pdf-highlight-rotation-import.php`. It builds one inherited `/Rotate 90` + nonzero `/CropBox` page and one direct `/Rotate 270` page, then applies highlight/underline review metadata to supplied marker/pdftext display-space spans.

Smoke output confirms:

- `page_rotations=[90,270]`
- `markup_count=2`
- `annotated_spans=2`
- `raw_decoy_annotated=false`
- `coordinate_spaces=[marker_pdftext_display]`
- no Python/models, pypdfium, external PDF tools, annotation rendering, or PDF actions executed.

## Verification

- Before slice: `php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php` passed with `1 test files, 50 assertions, 0 failures`.
- After slice: `php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php` passed with `1 test files, 70 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-highlight-rotation-import.php` emitted transformed display-space QuadPoint metadata and left raw decoy spans unannotated.
- Changed PHP lint and `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object/page-tree parser, annotation traversal, PDF array/string decoders, page box inheritance logic, and supplied marker/pdftext span geometry. Full upstream markerPDF Python/model/pdftext/pypdfium benchmark parity remains dependency-gated.

## Non-Overlap

This does not repeat the accepted text-markup QuadPoints extraction, text-markup border/popup metadata, annotation geometry review, or MarkerAppPreview page-box/UserUnit preview slices. The new behavior is specifically applying text-markup QuadPoints through inherited page boxes and page rotation before WordPress review spans are marked.
