# markerPDF Page Label NumberTree Viewer Preference Boundaries

## Source-Truth Boundary

- Upstream `sddai/markerPDF` `master` is still at the pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; a shallow `/tmp` clone was inspected for this slice.
- `marker/pdf/extract_text.py` uses pypdfium/pdftext page iteration (`len(doc)`, `page_range`, and `get_text_bounded()` per page) as the upstream page boundary. This native PHP slice keeps PDF catalog `/PageLabels` as page-break/review metadata aligned to native `/Contents` extraction without running pypdfium, pdftext, Python, or models.
- PDF catalog `/PageLabels` is a number tree: `/Kids` can be indirect, each node can declare integer `/Limits`, and `/Nums` keys should not allow stale/out-of-range label sections to override the current page count. `/ViewerPreferences` values are bounded catalog review metadata and should resolve indirect scalar/array operands while ignoring invalid names and counts.

## Implementation

- `PdfTextExtractor` now resolves indirect `/Kids` arrays and indirect `/Nums`, `/Limits`, `/S`, `/St`, and `/P` page-label operands.
- Page-label number-tree nodes now honor `/Limits`, ignore negative and out-of-page-range keys, and keep fallback one-based labels when no valid sections exist.
- `PdfMetadataExtractor` now resolves indirect `/ViewerPreferences` booleans, names, integer arrays, and integers; filters name-valued preferences to PDF-defined domains; requires positive paired `/PrintPageRange` values; and ignores non-positive `/NumCopies`.
- Added a WordPress smoke that emits page-break comments for bounded labels plus non-executing catalog-review metadata.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-label-viewer-prefs-boundaries.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed: 2 files, 622 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-label-viewer-prefs-boundaries.php` emitted `front-ii`, `Body 1`, and `App-AA` page labels; `ignored_stale_page_label_key=true`; `invalid_viewer_preference_filtered=true`; no external PDF tools executed.
- `php tools/run-tests.php lanes/markerpdf/tests` passed: 59 files, 2699 assertions, 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, number-tree helpers, catalog metadata parser, page `/Contents` extraction path, and WordPress example rendering.
