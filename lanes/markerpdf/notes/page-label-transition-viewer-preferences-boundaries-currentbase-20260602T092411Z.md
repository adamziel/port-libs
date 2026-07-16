# markerPDF Page Label Transition Viewer Preference Boundaries

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py` uses PDFium/pdftext page iteration (`len(doc)`, `page_range`, and per-page bounded text extraction) as the page boundary: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Upstream `marker_app.py` exposes PDF page previews and page counts before model conversion, so native WordPress review rows need page-aligned metadata without executing Python, pypdfium, models, JavaScript, or external PDF tools: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_app.py
- The PDF boundary used here is page-scoped presentation metadata: catalog `/PageLabels`, `/PageLayout`, `/PageMode`, `/ViewerPreferences`, and page `/Dur`, `/Trans`, and `/AA` action dictionaries.

## Implementation

- `PdfOutlineExtractor::getPageTransitionActionMetadata()` now annotates each page presentation row with one-based `page_number`, resolved `page_label`, and optional `catalog_view` metadata.
- `catalog_view` reuses the accepted `PdfMetadataExtractor` viewer-preference parser, so indirect operands and PDF-defined domains remain bounded.
- Page labels reuse the accepted `PdfTextExtractor` number-tree parser, so `/Limits` and stale out-of-page label keys cannot override current page presentation rows.
- The WordPress page-label/viewer-preference smoke now emits labeled transition/action review list rows in addition to page-break labels and catalog review metadata.

## Evidence

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-label-viewer-prefs-boundaries.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: 1 file, 153 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed: 3 files, 780 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-label-viewer-prefs-boundaries.php` emitted `transition_page_labels=["front-ii","Body 1"]`, `transition_styles=["Dissolve","Split"]`, `ignored_stale_page_label_key=true`, `invalid_viewer_preference_filtered=true`, and `all_page_actions_review_only=true`.
- `git diff --check -- lanes/markerpdf` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `451 -> 452`.
- Mapped native PDF semantics move `303 -> 304 / 78`.
- `UPSTREAM_TEST_MANIFEST.json` records `pdfPageLabelTransitionViewerPreferenceBoundaryBehaviors`.

## Non-Overlap

This does not repeat standalone `/PageLabels` number-tree extraction, standalone indirect `/ViewerPreferences` operand filtering, standalone page `/Dur` `/Trans` `/AA` action review metadata, catalog OpenAction `/Next` safety review, or destination-view operand parsing. The slice only composes the already accepted page-label and catalog-view boundaries into page transition/action review rows for WordPress import.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, page label number-tree parser, catalog metadata parser, page transition/action parser, and WordPress example renderer.
