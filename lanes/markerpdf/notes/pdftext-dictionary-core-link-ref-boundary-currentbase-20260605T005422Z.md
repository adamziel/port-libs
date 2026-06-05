# markerPDF pdftext dictionary core link/ref boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T005422Z`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Its `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF page dictionaries to `pdftext.extraction.dictionary_output(...)` before converting them into Marker page/span structures: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>.
- Current upstream Marker `PdfProvider` maps `span.get("url")` from pdftext spans into Marker text spans and stores page `refs` from the pdftext page dictionary as page-reference metadata: <https://raw.githubusercontent.com/datalab-to/marker/master/marker/providers/pdf.py>.
- `pdftext.extraction.dictionary_output(...)` merges link annotations before `keep_chars=false`, removes raw `chars`, but leaves span `url` fields and page `refs` available to callers: <https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py> and <https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/pdf/links.py>.

## Implemented Behavior

- `PdfTextDocumentExtractor` now preserves pdftext span `url` through the keep-chars false dictionary sanitizer while still dropping raw character payloads.
- `PdfTextBlockConverter` carries pdftext URLs as `pdftext_url` review metadata, promotes only safe `http`, `https`, `mailto`, `ftp`, and local URLs into the visible span `url`, and marks unsafe URIs as review-only.
- Page-level pdftext `refs` are recorded in `pdftext_source.refs` so WordPress import review can retain internal reference metadata without running Python/pdftext/PDFium/model workers.
- Added a WordPress smoke showing safe link Markdown promotion, unsafe JavaScript URI exclusion from visible text, page ref preservation, and raw char-payload omission.

## Verification

Red before implementation:

- A one-off `PdfTextDocumentExtractor` probe with span `url` values and page `refs` returned visible span keys without `url`, char-block span keys without `url`, and `pdftext_source` without `refs`.

Green after implementation:

- `php -l lanes/markerpdf/src/PdfTextBlockConverter.php` passed.
- `php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-link-ref-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` passed: 1 test file / 15 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/MarkdownPostProcessorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php` passed: 5 test files / 799 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-link-ref-currentbase.php` passed and emitted `safe_pdftext_url_promoted=true`, `unsafe_pdftext_url_review_only=true`, `pdftext_refs_preserved=true`, and `raw_chars_excluded=true`.

Focused delta: +2 focused PASS cases and +15 focused assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP pdftext dictionary conversion, dictionary-output sanitation, safe-URI policy, Markdown span merging, and WordPress smoke paths. Live `pdftext`, pypdfium/PDFium, Surya/Torch/OCR/layout/order models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat attachment/FileSpec related-file handling, parser/xref repair, annotation action extraction, rotated PDF annotation geometry, page-label/name-tree metadata, layout/order artifact alignment, normalized bbox scaling, sorting, blank-page handling, text normalization, CMap/font/width behavior, image/filter metadata, table/equation supplied-boundary work, or runtime preflight. The bounded behavior is pdftext dictionary-output span URL and page reference metadata at the core native conversion boundary.
