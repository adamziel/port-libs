# markerPDF pdftext dictionary character core boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T020100Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T020100Z`
Base accepted HEAD: `b3e31fcd254cdb56827a13df0f383fc8e9fe2950`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(..., keep_chars=False)` and stores the returned `page["blocks"]` as Marker `char_blocks`: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.extraction.dictionary_output()` deletes non-core block/line keys, drops span `chars` unless `keep_chars=True`, and for `keep_chars=True` only converts each character `bbox` to numeric coordinates: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py
- `pdftext.schema.Char` defines kept character dictionaries with `char`, `bbox`, `rotation`, `font`, and `char_idx`: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py

## Change

`PdfTextDocumentExtractor` now sanitizes supplied pdftext font dictionaries at the page-dictionary boundary:

- span `font` dictionaries keep only `name`, `flags`, `weight`, and `size`;
- kept character dictionaries now keep upstream-shaped `char`, `bbox`, `rotation`, `font`, and `char_idx`;
- legacy `c` aliases and raw/debug font payload keys are excluded before Marker `char_blocks` and WordPress review metadata.

This keeps optional `keepChars: true` review metadata useful for WordPress import audits without letting adapter/private payload keys cross the pdftext dictionary boundary.

## Red First

Before the source change, the focused regression failed:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`

Result: `FAIL keeps upstream-shaped character font dictionaries at the keep chars boundary`; expected character keys `[char,bbox,rotation,font,char_idx]`, actual keys retained legacy `c`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` => `1 test files, 38 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php` => `3 test files, 218 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php` emitted `legacy_c_alias_excluded=true`, `font_payload_excluded=true`, `visible_wordpress_text="Character dictionary import"`, and no Python/pdftext/model/external PDF tool execution.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, `keep_chars=false` raw character removal, optional `keepChars: true` retention, normalized bbox scaling, link/ref sanitation, block sorting, blank-page preservation, sparse layout/order matching, OCR/table supplied boundaries, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is only the core-key/font-payload boundary for supplied pdftext span and kept-character dictionaries.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
