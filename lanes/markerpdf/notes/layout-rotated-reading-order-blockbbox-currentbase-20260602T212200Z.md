# Layout Rotated Reading Order Block BBox

Slice: `layout-rotated-reading-order-blockbbox-currentbase`

Base accepted HEAD: `0e451709894623744c6f5d4ef8d1ef3a4870fcbb`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses `marker/layout/order.py::sort_blocks_in_reading_order`: order boxes are rescaled from `order.image_bbox` to `page.bbox`, then each block is assigned the ordering position with the greatest bbox intersection.
- Upstream `marker/pdf/extract_text.py::pdftext_format_to_blocks` builds marker `Page` objects from pdftext dictionaries and swaps page width/height for `/Rotate 90` and `/Rotate 270`, so `page.bbox` is marker/pdftext display geometry.
- Locked pdftext dependency `pdftext/pdf/chars.py` and `pdftext/pdf/utils.py` read PDFium page rotation and convert PDF page-space character boxes into device/display boxes before dictionary output. Native PHP page-space block boxes need the same bounded conversion before they can intersect display-space order boxes.

## Implementation

- `LayoutOrderer::sortBlocksInReadingOrder()` now uses a page-aware bbox for order matching and same-position sorting.
- Existing callers are unchanged by default. The transform only activates when a page declares `block_bbox_coordinate_space` or a block declares `bbox_coordinate_space`/`block_bbox_coordinate_space` as `pdf_page_user_space`/`pdf_page_space`/`page_user_space` and the page exposes `pdf_page_bbox` or `page_bbox`.
- The opt-in transform maps raw PDF page-user-space rectangles through `/Rotate 0/90/180/270`, source page boxes, and optional page `user_unit`/`page_user_unit` into marker/pdftext display coordinates before intersection with supplied order boxes.

## WordPress Smoke

Added `examples/wordpress-pdf-rotated-reading-order-import.php`.

The smoke constructs a `/Rotate 90` page where raw PDF page-space block bboxes would otherwise sort as the second column before the first. It emits ordered Gutenberg paragraph blocks:

- `First rotated column introduces the import.`
- `Second rotated column lists media checks.`

No Python, model execution, pypdfium/PIL rendering, PDF action execution, decryption, or external PDF tools are used.

## Verification

- Before slice focused baseline: `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed with `1 test files, 20 assertions, 0 failures`.
- Red-first current-base probe: direct `LayoutOrderer` page-space fixture sorted `Right raw first | Left raw second`.
- After implementation: `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed with `1 test files, 24 assertions, 0 failures`.
- Example smoke: `php lanes/markerpdf/examples/wordpress-pdf-rotated-reading-order-import.php` emitted ordered rotated Gutenberg paragraph blocks.
- PHP lint passed for `src/LayoutOrderer.php`, `tests/LayoutOrdererTest.php`, and `examples/wordpress-pdf-rotated-reading-order-import.php`.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. This reuses the native supplied-layout/order boundary, the existing bbox intersection/rescale helper, and the page-rotation/page-box conversion semantics already established for text-markup review spans. Full upstream markerPDF runner parity remains gated by Python/model/pdftext/pypdfium/Streamlit/FastAPI dependencies.

## Non-Overlap

This does not repeat accepted text-markup QuadPoint rotation, MarkerAppPreview page-box/UserUnit sizing, page graphics-state `cm` text-position transforms, table rotated-rowspan header grids, StructTree/PageTree reading-order extraction, or supplied OCR table geometry. The new behavior is specifically matching rotated raw PDF page-space block bboxes to supplied layout/order boxes for WordPress reading-order preservation.
