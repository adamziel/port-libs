# Outline Structure Destination Action Context Current Base

Micro-slice: `outline-structure-destination-action-context-currentbase`

Source truth:

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/cleaners/toc.py::get_pdf_toc`, delegates TOC extraction to the PDF engine and keeps rows shaped as title, level, and zero-based page: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/cleaners/toc.py
- Upstream `marker/pdf/extract_text.py::get_text_blocks` returns page blocks and TOC metadata separately through `pdftext.dictionary_output(...)`, so outline/action dictionaries are navigation metadata rather than visible page text: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF outline dictionaries can carry structure/style (`/Count`, `/F`, `/C`, `/Parent`, `/First`, `/Next`) while `/Dest` can resolve through a name tree to a GoTo action dictionary with chained `/Next` followups. WordPress import needs that action stack as review metadata only.

Implementation:

- `PdfOutlineExtractor::getOutlineStructureDestinationPageContext()` now enriches structured outline rows with `destination_action_*` metadata when the row destination resolves to a local action dictionary.
- The added context includes the action-backed destination name/object/type, action type and safety summaries, chained count, nested non-executing review rows, and the resolved destination action target page label, view operands, transition, and page actions.
- Existing `getPdfToc()` and destination-view fields remain stable; action operands, URI strings, JavaScript payloads, page additional actions, and outline dictionaries stay out of visible WordPress paragraph text.
- Added `wordpress-pdf-outline-structure-destination-action-context-currentbase.php` to prove the Gutenberg-facing path emits review-only metadata while importing only page-stream text.

Red-first evidence:

- Before the source edit, `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php` failed with missing `destination_action_name` on structured outline rows: 1 file, 13 assertions, 2 failures.

Focused evidence:

- `php -l lanes/markerpdf/src/PdfOutlineExtractor.php && php -l lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-outline-structure-destination-action-context-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineStructureDestinationActionContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineStructureDestinationPageContextCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineDestinationActionContextCurrentBaseTest.php` passed with 3 files, 211 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php` passed with 16 files, 1080 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-structure-destination-action-context-currentbase.php` passed and emitted `destination_action_name=DeckAction`, `destination_action_types=[GoTo,URI,JavaScript]`, `destination_action_safeties=[local-destination,review-uri,blocked-javascript]`, `destination_action_target_view_mode=XYZ`, `destination_action_target_transition=Push`, and `visible_text_excludes_outline_action_context=true`.
- `php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'` passed.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- `phpPass` moves `799 -> 800`.
- WordPress scenarios move `799 -> 800`.
- Mapped markerPDF semantics add `pdfOutlineStructureDestinationActionContextCurrentBase`.

Non-overlap:

- This does not repeat accepted plain outline named-destination resolution, destination Fit/XYZ view normalization, destination action target context rows, outline structure/page context rows, name-tree limits, remote GoToR/GoToE, launch/thread action context, page PieceInfo/thread enrichment, OpenAction review, or parser/xref safety work.
- The bounded new behavior is the combined edge where a structured collapsed/styled outline row resolves through `/Dest` to an action dictionary and needs the same non-executing destination action context on the structured outline row itself.

Dependency closure:

- No new support component is needed. This reuses native PDF object parsing, name-tree destination resolution, bounded action-chain review, outline structure/style parsing, page-label extraction, page transition/action review metadata, and visible text extraction. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, and external OCR/rendering helpers.
