# markerPDF outline root zero-count boundary

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T131944Z`

Base accepted HEAD: `26ea7c19217f7dcc8974578c7ce9b5bc8761d389`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark rows as metadata through `marker/cleaners/toc.py::get_pdf_toc`, delegated to the PDF document backend before model execution.
- PDF outline root `/Count` describes the visible outline tree. A root `/Count 0` is an empty visible outline boundary, so contradictory `/First` rows are stale/damaged review input, not WordPress navigation rows.
- Outline titles, JavaScript actions, and remote GoToR targets remain metadata-only and must not become Gutenberg paragraph text.

## Implementation

- `PdfOutlineExtractor` now checks catalog `/Outlines` root `/Count 0` before rich TOC rows, navigation review rows, remote action rows, and structure destination page-context rows are traversed.
- `PdfMetadataExtractor` preserves root outline review fields such as root object, first/last object, and declared visible count, but emits zero child items and zero resolved destinations when the root count is zero.
- `PdfTextExtractor::extractOutlineMetadata()` applies the same lightweight upstream-style `pdf_toc` boundary.
- The WordPress smoke renders only page text and a review comment showing zero outline rows; it rejects stale outline titles/actions if they appear in metadata, navigation, lightweight TOC, or visible text.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootZeroCountBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves root outline metadata while suppressing Count zero child rows
PASS applies root Count zero boundary to TOC navigation and action review
PASS keeps root Count zero rows out of lightweight upstream pdf_toc metadata

1 test files, 47 assertions, 0 failures
```

Adjacent outline metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataRootZeroCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1417 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-root-zero-count-boundary-currentbase.php
```

The smoke emits `declared_visible_count=0`, `metadata_item_count=0`, `toc_count=0`, `navigation_count=0`, `lightweight_toc_count=0`, `stale_outline_rows_suppressed=true`, `stale_outline_actions_suppressed=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1860 -> 1863` from 3 new focused PASS cases.
- `wordpressScenarios` moves `1688 -> 1689` from the root zero-count outline smoke.
- No mapped-denominator change is claimed; this is a focused native PDF outline metadata boundary.

## Non-Overlap

This does not repeat accepted outline root type validation, root collapsed count metadata, item `/Count 0` child suppression, lightweight item zero-count suppression, Prev/Last/Parent/title/titleless/comment/generation/EOF/xref-owner/object-stream boundaries, named destinations, page labels, transition/thread/action-chain enrichment, XMP/Info ownership, page geometry, annotations, forms, security, image, font, CMap, stream-filter, or supplied-boundary table/equation slices. The bounded new behavior is only catalog `/Outlines` root `/Count 0` suppressing contradictory child outline rows while preserving root review metadata.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF object scanning, dictionary/value parsing, outline traversal, metadata extraction, lightweight TOC extraction, and WordPress smoke rendering. Full live OCR, Surya/Texify/Torch model execution, PDFium/pypdfium rendering, PIL, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
