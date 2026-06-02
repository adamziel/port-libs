# Outline Destination Action Page Label Structure Current Base

Micro-slice: `outline-destination-action-page-label-structure-currentbase`

Source truth:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/cleaners/toc.py::get_pdf_toc`, delegates PDF bookmark extraction to the PDF engine and preserves title, level, and zero-based page rows: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` extracts TOC metadata separately from `pdftext.dictionary_output(...)` page text, so outline/action dictionaries are navigation metadata rather than visible body text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF name-tree destinations may resolve an outline `/Dest` to an action dictionary with `/S /GoTo`, `/D`, and chained `/Next` actions; tagged pages expose `/StructTreeRoot`, `/ParentTree`, and marked-content MCIDs. WordPress imports need stable review fields for the action target without executing actions or leaking operands into visible paragraphs.

Implementation:

- `PdfOutlineExtractor::destinationActionTargetContext()` now includes `destination_action_target_page_number` and copies any existing `page_object` if present.
- Destination action target tagged-content rows now also produce compact review summaries:
  - `destination_action_target_structure_mcids`
  - `destination_action_target_structure_raw_roles`
  - `destination_action_target_structure_roles`
  - `destination_action_target_structure_text`
  - `destination_action_target_structure_objects` when available
- Added `PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php` with a named outline destination that resolves to a local GoTo action dictionary targeting a labeled tagged page and chained URI/JavaScript review rows.
- Added `wordpress-pdf-outline-destination-action-page-label-structure-currentbase.php` to prove Gutenberg-facing metadata carries the target label/structure summaries while visible text stays limited to page content.

Red-first evidence:

Before the source edit, the new focused test failed because the target page number and compact structure summary fields were absent:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php
```

Result: `1 test files, 18 assertions, 1 failures`; failure expected `[2,2,2]` for `destination_action_target_page_number` and got an empty array.

Focused verification:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php
```

Result: `1 test files, 38 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineDestinationActionPageLabelStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNameTreeActionStructureCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
```

Result: `5 test files, 534 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-outline-destination-action-page-label-structure-currentbase.php
```

Result: passed and emitted `target_page_labels=["Chapter 12","Chapter 12","Chapter 12"]`, `target_page_numbers=[2,2,2]`, `target_structure_mcids=[0,1]`, `target_structure_raw_roles=["ChapterTitle","ChapterBody"]`, `target_structure_roles=["H1","P"]`, `target_structure_text=["Destination heading from structure","Destination body from structure"]`, and all execution flags false.

Status delta:

- Focused behavior tests move `870 -> 872` pass / `0` fail in `lane-status.json`.
- Mapped markerPDF semantics move `614 -> 615 / 78` in `UPSTREAM_TEST_MANIFEST.json`.

Non-overlap:

This does not repeat accepted ordinary named-destination resolution, name-tree `/Limits`, outline structure/style rows, destination action target transition/context rows, name-tree action structure rows, article-thread target context, page PieceInfo target review, or OpenAction review. The bounded new behavior is specifically stable compact page-label plus StructElem summaries on non-executing outline destination action review rows.

Dependency closure:

No new support component is needed. This reuses native PDF object parsing, outline/name-tree destination resolution, action-chain review extraction, PageLabels extraction, StructTree tagged-content extraction, and visible-text boundaries. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/OCR/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers; none were executed for this bounded PHP slice.
