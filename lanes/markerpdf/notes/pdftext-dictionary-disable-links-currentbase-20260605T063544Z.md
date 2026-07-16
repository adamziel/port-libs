# markerPDF pdftext dictionary disable links current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T063544Z`

## Source Truth

- Upstream markerPDF is pinned in the lane manifest to `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF extraction to `pdftext.extraction.dictionary_output(...)`, then converts those dictionaries into Marker `Page` blocks.
- Upstream `pdftext.extraction.dictionary_output(...)` accepts `disable_links`; when false it calls `add_links_and_refs(pages, pdf)`, and when true it skips that link/ref annotation before block/line/span post-processing.

Source links used for this slice:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py

## Implemented Behavior

- `PdfTextDocumentExtractor::getTextBlocks()` now accepts `disableLinks: true`, records it as `pdftext_options.disable_links`, strips supplied page `refs`, and removes span `url` values before `PdfTextBlockConverter` can promote them to WordPress Markdown links or review URLs.
- `PdfTextDocumentExtractor::getOrderedTextBlocks()` passes the same option through for supplied layout/order paths.
- Added a focused WordPress smoke proving safe and unsafe supplied span URLs are absent, page refs are absent, and visible Gutenberg text stays plain when the option is enabled.

## Verification

Red before implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` failed at `honors pdftext disable_links at the dictionary core boundary` with `Unknown named parameter $disableLinks`.

Green after implementation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed: `1 test files, 81 assertions, 0 failures`.

Focused delta: +1 focused PASS case and +11 assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`.

Additional verification is recorded in the final handoff for PHP lint, the WordPress smoke, manifest/status JSON validity, and `git diff --check -- lanes/markerpdf`.

## Dependency Closure

No new support component is needed. This reuses native PHP pdftext dictionary conversion, metadata/options preservation, span/link sanitization, Markdown post-processing, and WordPress smoke paths. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted pdftext dictionary link/ref preservation, keep-chars sanitation, post-processing, sorting, blank-page handling, layout/order artifact alignment, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, or equation/image supplied-boundary work. The bounded behavior is specifically `dictionary_output(..., disable_links=True)` link/ref suppression at the supplied dictionary core boundary before WordPress import.
