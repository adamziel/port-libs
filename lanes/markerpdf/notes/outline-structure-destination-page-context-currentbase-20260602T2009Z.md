# Outline Structure Destination Page Context Current Base

Micro-slice: `outline-structure-destination-page-context-currentbase`

Source truth:

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/cleaners/toc.py::get_pdf_toc`, delegates to the PDF document TOC and keeps upstream TOC rows shaped as title, level, and zero-based page.
- PDF outline item dictionaries add structure/review context around that upstream TOC row: `/Count` carries descendant count and open/closed state, `/F` carries italic/bold flags, `/C` carries RGB text color, and `/Parent`/`/Prev`/`/Next`/`/First`/`/Last` define outline structure.

Implementation:

- `PdfOutlineExtractor::getOutlineStructureDestinationPageContext()` now emits review-only outline rows with structure links, collapsed/expanded state, style flags, text color, resolved destination view metadata, page labels, page objects, target page transitions, and target page actions.
- `getNavigationReviewMetadata()` now uses those richer outline rows while preserving the existing `getPdfToc()` and `getPdfTocWithDestinationViews()` compatibility shape.
- `wordpress-pdf-outline-structure-destination-page-context-currentbase.php` demonstrates a WordPress import path where collapsed/styled outline metadata is preserved as review metadata while visible paragraphs remain page-stream text only.

Focused evidence:

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-structure-destination-page-context-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed with 2 files, 400 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php` passed with 14 files, 977 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-structure-destination-page-context-currentbase.php` passed and emitted `visible_text_excludes_outline_structure=true`.

Status delta:

- `phpPass` moves `758 -> 760`.
- mapped markerPDF semantics move `540 -> 541 / 78`.

Non-overlap:

- This does not repeat accepted named destination, Fit/XYZ view, name-tree limits, destination action context, transition/action propagation, remote GoToR/GoToE, article-thread, or page PieceInfo outline slices. The new behavior is outline item dictionary structure and style metadata combined with already resolved destination page context.

Dependency closure:

- No new support component is needed. This reuses native PDF object parsing, outline/name-tree destination resolution, page-label extraction, page transition/action review metadata, and visible-text extraction boundaries without Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, model downloads, or PDF action execution.
