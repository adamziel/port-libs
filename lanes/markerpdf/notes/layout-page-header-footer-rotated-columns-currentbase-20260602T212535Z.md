# Layout Page Header Footer Rotated Columns Current Base

Slice: `layout-page-header-footer-rotated-columns-currentbase`

Source truth:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` runs `marker/layout/order.py::sort_blocks_in_reading_order()` by assigning each block to the supplied order box with maximum overlap, after rescaling `order.image_bbox` to `page.bbox`, then pinning `Page-header` blocks before body blocks and `Footnote` / `Page-footer` blocks after body blocks.
- Upstream `marker/pdf/extract_text.py::pdftext_format_to_blocks()` stores the page-local `rotation` and swaps the page bbox axes for 90/270-degree pages before downstream layout ordering.
- Relevant PDF/page behavior: a 90/270-degree page may still have preview or static order-image coordinates in the pre-rotation page orientation, while marker/pdftext block bboxes are compared in the display/page-local coordinate space.

Implementation:

- `LayoutOrderer::rescaleOrderBbox()` now detects 90/270-degree pages whose `order.image_bbox` aspect ratio matches the pre-rotation page orientation.
- Those order boxes are rotated into marker/pdftext page coordinates before the existing upstream-style rescale and maximum-overlap assignment run.
- Header/footer pinning stays on the accepted path: `Page-header` is emitted before body content, and `Page-footer` / `Footnote` stays after body content.
- `examples/wordpress-layout-rotated-columns-currentbase.php` demonstrates a WordPress import smoke with a rotated two-column page: visible paragraphs emit first-column then second-column text, while header/footer artifacts remain review metadata and are excluded from visible Gutenberg body text.

Focused behavior:

- The new `LayoutOrdererTest.php` case supplies a 90-degree page with page bbox `[0,0,800,600]`, but order-image bbox `[0,0,600,800]`.
- Before the source change, the two body blocks had no reliable overlap in page space and stayed in supplied input order (`right`, then `left`).
- After the source change, unrotated order boxes rotate into display page space, so the body blocks sort as `left`, then `right`, with the page header first and page footer last.

Verification:

- `php -l lanes/markerpdf/src/LayoutOrderer.php` passed.
- `php -l lanes/markerpdf/tests/LayoutOrdererTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-layout-rotated-columns-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php` passed with 1 file, 21 assertions, 0 failures, and 9 PASS lines.
- `php lanes/markerpdf/examples/wordpress-layout-rotated-columns-currentbase.php` emitted `body_columns_in_reading_order=true`, `header_pinned_first=true`, `footer_pinned_last=true`, `edge_artifacts_hidden_from_visible_body=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

No new support component is needed. This slice reuses the existing native layout ordering, pdftext page-rotation metadata, Markdown/WordPress smoke path, and static supplied order-model fixtures. Full upstream runner parity remains gated by Poetry plus pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, OCR tooling, Streamlit/FastAPI runtime paths, and live benchmark workflows.

Non-overlap:

This does not repeat accepted page-box/UserUnit preview sizing, rotated text-markup QuadPoints, PDF page transition/action metadata, table rotated-header grids, JSON/output/runtime handoffs, or CID font width work. The bounded behavior is specifically rotating pre-rotation order-image boxes into marker/pdftext page space before layout ordering and page-edge pinning for rotated multi-column imports.
