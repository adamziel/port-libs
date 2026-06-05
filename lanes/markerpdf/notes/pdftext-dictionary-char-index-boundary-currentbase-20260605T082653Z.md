# markerPDF pdftext dictionary character-index boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T082653Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T082653Z`
Base accepted HEAD: `ed6139905ed8ab8ca3c9ea6f51c61c4c55ce5d76`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `sddai/markerPDF` `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF pages to `pdftext.extraction.dictionary_output(...)` before converting returned dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.schema.Span` defines `char_start_idx` and `char_end_idx` as integer fields, and `pdftext.schema.Char` defines kept `char_idx` as an integer field: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/schema.py

## Change

`PdfTextDocumentExtractor` and the public `PdfTextBlockConverter` now fail closed on fractional pdftext character indexes instead of silently truncating them:

- span `char_start_idx` must be a finite integer-valued number;
- span `char_end_idx` must be a finite integer-valued number;
- kept-character `char_idx` must be a finite integer-valued number;
- whole-number floats such as `3.0` still normalize to integers, which keeps JSON-decoded supplied dictionaries usable.

This prevents malformed supplied dictionaries from shifting WordPress review metadata or linkable character ranges to a different text offset while preserving valid pdftext-style integer indexes.

## Red First

Before the source change, the focused regression failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL rejects fractional pdftext character indexes before WordPress rendering
Expected exception InvalidArgumentException was not thrown
1 test files, 98 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 104 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `1 test files, 40 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `3 test files, 412 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-index-boundary-currentbase.php
```

The smoke emitted `integral_float_indexes_normalized=true`, `fractional_span_start_rejected=true`, `fractional_span_end_rejected=true`, `fractional_char_index_rejected=true`, `visible_wordpress_text="Indexed dictionary text"`, and no Python/pdftext/model/external PDF tool execution.

Focused delta: +2 focused TestRunner PASS cases and +10 focused assertions across `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php` and `PdfTextBlockConverterTest.php`. Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext link/ref preservation, ref integer validation, disable_links behavior, keep_chars minimal dictionary inference, character/font payload sanitation, normalized/off-page bbox scaling, span script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior. The bounded behavior is only fail-closed integer validation for pdftext span and kept-character index metadata at the dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
