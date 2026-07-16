# Outline Name-Tree Action Structure Current Base

Micro-slice: `outline46-rebase-nametree-action-structure-currentbase`

Source truth:

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/cleaners/toc.py::get_pdf_toc`, delegates to the PDF document TOC and keeps upstream TOC rows shaped as title, level, and zero-based page: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` extracts TOC separately from pdftext/PDFium page text, so PDF outline/action dictionaries remain metadata rather than visible body text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF outline items can carry `/Count`, `/F`, `/C`, sibling/child links, and `/Dest`; destination name trees may resolve a named destination to an action dictionary with `/S /GoTo`, `/D`, and chained `/Next` actions. This slice keeps that richer structure review-only for WordPress imports.

Implementation:

- `PdfOutlineExtractor::outlineActionReviewRows()` now attaches prefixed `outline_*` structure/style metadata to outline action review rows.
- Name-tree destination action dictionaries now preserve collapsed/expanded state, outline counts, parent/child/sibling object links, style flags, text color, target page labels, transitions, page actions, and StructTree target roles/text on the same review rows.
- Added `PdfOutlineNameTreeActionStructureCurrentBaseTest.php` with a `/Names /Dests` action dictionary fixture whose `GoTo` target points at tagged content and whose `/Next` chain includes URI and JavaScript review rows.
- Added `wordpress-pdf-outline-nametree-action-structure-currentbase.php` to show the WordPress-safe review payload and visible H1/paragraph output without executing actions.

Focused evidence:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineNameTreeActionStructureCurrentBaseTest.php` passed: 1 test file, 87 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php` passed: 16 test files, 1117 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-nametree-action-structure-currentbase.php` passed and emitted `visible_text_excludes_outline_action_structure=true`, `outline_structure_state=collapsed`, target roles `H1/P`, and all execution flags false.

Status delta:

- Focused behavior tests move `810 -> 812` pass / `0` fail in `lane-status.json`.
- Mapped markerPDF semantics are expected to move `569 -> 570 / 78` after integration.

Non-overlap:

- This does not repeat accepted ordinary named-destination resolution, name-tree `/Limits`, destination Fit/XYZ operand normalization, destination action target page context, remote GoToR/GoToE review, article-thread bead navigation, page PieceInfo target review, or plain outline structure/style rows.
- The new behavior is specifically the combined name-tree destination action review row carrying the source outline item's collapsed/style structure and the target page's StructTree context through chained action rows.

Dependency closure:

- No new support component is needed. This reuses native PDF object parsing, outline/name-tree destination resolution, action-chain review extraction, page-label extraction, page transition/action review, StructTree tagged-content extraction, and visible-text boundaries.
- Full upstream runner parity remains dependency-gated on Python/pdftext/pypdfium/PDFium, Streamlit/FastAPI runtime paths, OCR/model dependencies, and benchmark scripts, which were not executed for this isolated slice.
