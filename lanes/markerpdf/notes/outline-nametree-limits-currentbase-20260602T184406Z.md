# Outline NameTree Limits Current Base

Micro-slice: `outline-nametree-currentbase`

Base accepted HEAD: `4bfec4c2ed04ec45b69266408311f6827e291bfb`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/cleaners/toc.py::get_pdf_toc` delegates bookmark resolution to the PDF engine via `doc.get_toc(max_depth=...)`, preserving resolved title, level, and page index as navigation metadata: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` keeps page text blocks separate from TOC metadata returned by `get_pdf_toc`, so destination dictionaries and action operands stay out of visible page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- ISO 32000-1/PDF 1.7 name-tree nodes use `/Limits` to identify the least and greatest keys contained in descendant leaf `/Names` arrays, and keys are sorted lexically: https://www.levien.com/pdf/PDF32000_2008.pdf

## Red Evidence

Before changing `PdfOutlineExtractor`, the new focused fixture failed:

`php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php`

Result: `1 test files, 9 assertions, 2 failures`.

The stale out-of-limits `/Limits [(N) (Z)]` leaf repeated `DeckStart` and incorrectly overrode the valid `/Limits [(A) (M)]` leaf. That moved the first outline row to page 1 and surfaced stale outline action review rows.

## Implementation

- `PdfOutlineExtractor::collectNameTreeDestinations()` now carries active `/Limits` ranges through name-tree child traversal.
- Leaf `/Names` entries are collected only when the decoded destination key falls inside every active limits range.
- Added `nameTreeLimits()` and `nameWithinNameTreeLimits()` helpers.
- Added `PdfOutlineNameTreeLimitsCurrentBaseTest.php` to prove TOC rows, rich destination-view metadata, OpenAction destination review, and visible text boundaries all use the in-limits current destination.
- Added `wordpress-pdf-outline-nametree-limits-currentbase.php` as the WordPress smoke path.

## Verification

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-outline-nametree-limits-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNameTreeLimitsCurrentBaseTest.php` passed: `1 test files, 24 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php` passed: `1 test files, 299 assertions, 0 failures`.
- `php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfOutline.*\.php$' | sort)` passed: `8 test files, 557 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-nametree-limits-currentbase.php` passed and emitted `toc_pages=[0,0]`, `toc_destinations=["DeckStart","DeckReview"]`, `outline_action_count=0`, `open_action_page=0`, and `visible_text_excludes_stale_action=true`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `648 -> 649`.
- Mapped markerPDF/PDF semantics move `474 -> 475 / 78`.

## Non-Overlap

This does not repeat accepted plain outline named-destination resolution, indirect name-tree destination dictionaries, destination Fit/XYZ operand normalization, outline destination action review, named-destination action thread/page-review propagation, OpenAction next-chain propagation, page transition/action metadata, or article-thread bead navigation. The bounded behavior is only destination name-tree `/Limits` enforcement before collecting outline/OpenAction destination names.

## Dependency Closure

No new support component is needed. This reuses the lane-native PDF object parser, name-tree destination resolver, outline/OpenAction navigation review paths, PageLabels parser, and visible text extractor. Full upstream markerPDF parity remains gated by pdftext, pypdfium2/PDFium, Surya/OCR, PIL rendering, tabled-pdf, Texify/Torch model downloads, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers, none of which were run for this bounded PHP slice.
