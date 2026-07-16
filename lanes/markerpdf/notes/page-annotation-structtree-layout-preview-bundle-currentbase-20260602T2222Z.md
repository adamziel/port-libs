# markerPDF Page Annotation StructTree Layout Preview Bundle Current Base

## Source Truth

- Upstream pinned `marker_app.py` opens uploaded PDFs through `pypdfium2.PdfDocument`, reports `len(doc)`, renders a selected one-based page at `dpi / 72`, converts the result to RGB, and shows it in Streamlit. Source: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py
- Upstream pinned `marker/layout/layout.py` assigns supplied model layout labels by bbox intersection after rescaling layout coordinates into page coordinates, then merges blocks that map to the same layout box. Source: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/layout.py
- Upstream pinned `marker/layout/order.py` likewise rescale-matches ordering bboxes to page bboxes and preserves page/block overlay geometry before reading-order sorting. Source: https://raw.githubusercontent.com/datalab-to/marker/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/layout/order.py
- PDF parser behavior for this slice: page dictionaries carry `/Annots`; annotations use singular `/StructParent`; tagged PDF `/StructTreeRoot /ParentTree` maps both page `/StructParents` MCID arrays and annotation OBJR rows; page `/PieceInfo` and structure-element `/AF` FileSpec payloads are review metadata, not visible text.

## Implementation

- Added `MarkerAppPreview::getPageLayoutPreviewBundle()` as a native PHP marker_app preview bundle.
- The bundle reuses current native parsers instead of introducing a new PDF parser:
  - `getPageImagePlan()` for page image geometry, DPI scale, page boxes, rotation, UserUnit, and pypdfium-style page index;
  - `PdfPagePropertyExtractor` for page `/StructParents`, ParentTree marked content, PieceInfo, text-markup rows, and annotation StructParent review rows;
  - `PdfAnnotationExtractor` for current page annotation rows, actions, structure parent rows, and associated FileSpec checksum metadata;
  - supplied Marker/pdftext pages for layout block overlay rows.
- Overlay rows expose rendered-image pixel bboxes for layout blocks, annotations, text-markup quads, and annotation StructParent rows. They are marked review-only where appropriate and carry `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pdf_actions=false`.
- Added a WordPress smoke that emits visible paragraphs only for page content while placing the preview bundle in HTML comments.

## Verification

- New focused test before implementation would fail because `MarkerAppPreview::getPageLayoutPreviewBundle()` did not exist.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeLayoutPreviewBundleCurrentBaseTest.php`
  - Passed: `1 test files, 91 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotationStructTreeLayoutPreviewBundleCurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationStructParentAssociatedActionCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructTreeAnnotationPieceInfoCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php`
  - Passed: `7 test files, 490 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-page-annotation-structtree-layout-preview-currentbase.php`
  - Passed; emitted `layout_block_count=2`, `annotation_count=2`, `text_markup_annotation_count=1`, `annotation_structure_parent_row_count=2`, `structure_marked_content_count=2`, and visible paragraphs only for `Visible preview body` plus `Visible caption`.
- `php -l lanes/markerpdf/src/MarkerAppPreview.php`
  - Passed.
- `php -l lanes/markerpdf/tests/PdfPageAnnotationStructTreeLayoutPreviewBundleCurrentBaseTest.php`
  - Passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-annotation-structtree-layout-preview-currentbase.php`
  - Passed.
- `git diff --check -- lanes/markerpdf`
  - Passed.

## Status Delta

- `phpPass`: `883 -> 884`.
- `wordpressScenarios`: `883 -> 884`.
- Focused assertion evidence added: `91` assertions in the new behavior test; adjacent gate confirms `490` assertions across preview/annotation/StructTree/markup files.
- Root harness: not run; this is an isolated micro-slice.

## Non-Overlap

This does not repeat accepted standalone page annotation StructParent rows, annotation action context, page StructTree PieceInfo rows, text-markup span context, page-box crop/UserUnit preview planning, runtime preview artifacts, StructTree clipping, page-associated file review, or layout ordering/annotation helpers. The new behavior is specifically the marker_app-style page preview bundle that composes those existing review rows with supplied layout block overlay bboxes for WordPress preview payloads.

## Dependency Closure

No new support component is needed. The slice reuses native PDF object scanning, page preview geometry, annotation extraction, StructTree ParentTree traversal, FileSpec checksum review, supplied layout pages, and text-markup span enrichment. Full upstream markerPDF parity remains gated by live pypdfium/PIL rendering, pdftext, Surya/OCR, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and benchmark tooling; none were executed for this bounded PHP slice.
