# markerPDF pdftext dictionary keep_chars validation current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T034002Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T034002Z`
Base accepted HEAD: `9cd18b46280b1cd320145a54beb7498135300e50`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` over the selected page range and converts those dictionaries into Marker page blocks without OCR/model execution: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext==0.3.18` `dictionary_output()` removes `span["chars"]` unless `keep_chars=True`; when `keep_chars=True`, it keeps character dictionaries and converts each character bbox before returning page dictionaries: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/extraction.py
- The pdftext public structured-output contract describes kept character dictionaries with `char`, `bbox`, `rotation`, `char_idx`, and `font` metadata.

## Change

`PdfTextDocumentExtractor` now validates kept character dictionaries after payload-key sanitation and bbox unnormalization:

- requires `char`, `bbox`, `rotation`, `font`, and `char_idx` for `keepChars: true`;
- validates character text, numeric bbox coordinates, numeric rotation and character index, and font `name`, `weight`, `size`, and optional `flags`;
- preserves the existing upstream-shaped character rows and raw/font payload exclusion before WordPress review metadata.

## Red First

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
FAIL rejects malformed pdftext keep chars rows before WordPress rendering
Expected exception InvalidArgumentException was not thrown
1 test files, 55 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextDocumentExtractor.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
1 test files, 59 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
3 test files, 276 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php
```

The WordPress smoke emitted `malformed_keep_chars_row_rejected=true`, `legacy_c_alias_excluded=true`, `font_payload_excluded=true`, `visible_wordpress_text=Character dictionary import`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext page-range slicing, `keep_chars=false` raw character removal, optional `keepChars: true` retention, normalized bbox scaling, character/font payload-key allowlisting, link/ref sanitation, span script flags, block sorting, blank-page preservation, sparse layout/order matching, OCR/table supplied boundaries, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, or runtime preflight. The bounded behavior is only fail-closed validation for malformed supplied pdftext kept-character rows.

## Dependency Closure

No new support component is needed. This reuses native supplied pdftext dictionary conversion, metadata sanitation, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
