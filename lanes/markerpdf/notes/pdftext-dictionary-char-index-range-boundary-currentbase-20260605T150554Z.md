# markerPDF pdftext dictionary character-index range boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T150043Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T150043Z`
Base accepted HEAD: `0707d842b016ee542fe2234818daaef87fcd00c8`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF pages to `pdftext.extraction.dictionary_output(...)` before `pdftext_format_to_blocks()` converts returned dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` inference derives `char_start_idx` from the first span character and `char_end_idx` from the last span character before dictionary output: https://raw.githubusercontent.com/VikParuchuri/pdftext/v0.3.18/pdftext/inference.py

## Change

`PdfTextDocumentExtractor` and `PdfTextBlockConverter` now fail closed on impossible pdftext character index ranges:

- span `char_start_idx` must be less than or equal to `char_end_idx`;
- kept-character `char_idx` values must stay within the parent span range when `keepChars: true`;
- single-character spans with equal start/end indexes remain valid;
- whole-number floats still normalize to integer metadata.

This prevents malformed supplied pdftext dictionaries from shifting WordPress review metadata or linkable character ranges outside the actual span boundary.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `FAIL rejects impossible pdftext character index ranges before WordPress rendering`; expected `InvalidArgumentException` was not thrown; `1 test files, 160 assertions, 1 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `FAIL rejects inverted span character indexes at the converter boundary`; expected `InvalidArgumentException` was not thrown; `1 test files, 43 assertions, 1 failures`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 166 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `1 test files, 45 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `3 test files, 498 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-char-index-boundary-currentbase.php
```

The smoke emitted `integral_float_indexes_normalized=true`, `fractional_span_start_rejected=true`, `fractional_span_end_rejected=true`, `fractional_char_index_rejected=true`, `inverted_span_range_rejected=true`, `char_before_span_range_rejected=true`, `char_after_span_range_rejected=true`, `visible_wordpress_text="Indexed dictionary text"`, and no Python/pdftext/model/external PDF tool execution.

Focused delta: +2 focused TestRunner PASS cases and +8 focused assertions over the prior accepted pdftext dictionary character-index boundary. Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted fractional index validation, pdftext link/ref preservation, ref integer validation, `disable_links`, keep-chars minimal dictionary inference, character/font payload sanitation, normalized/off-page bbox scaling, script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior. The bounded behavior is only fail-closed span/character index range validation at the pdftext dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
