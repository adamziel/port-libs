# markerPDF pdftext dictionary inferred character range current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T184555Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T184555Z`
Base accepted HEAD: `7639a657de450051d770a4d1b4b5bc75b5240c02`

## Source Truth

- Upstream markerPDF remains pinned at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(...)` and then converts each selected dictionary page into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Locked `pdftext` inference derives `span["char_start_idx"]` from the first kept character row and `span["char_end_idx"]` from the last kept character row before dictionary output returns the page dictionaries: https://raw.githubusercontent.com/datalab-to/pdftext/v0.3.18/pdftext/inference.py

## Change

`PdfTextDocumentExtractor` now infers missing parent span `char_start_idx` and `char_end_idx` metadata from kept-character `char_idx` rows when `keepChars: true`.

The sanitizer still fails closed when:

- kept character rows are missing usable indexes and the parent span has no range;
- inferred or supplied parent ranges are inverted;
- any kept character index falls outside the final parent span range.

Raw character payload keys remain excluded before WordPress paragraph rendering.

## Red First

Before the source change, the focused regression failed as expected:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 178 assertions, 1 failures`.

Failing case: `infers pdftext span character ranges from kept character indexes` expected `char_start_idx=30`, actual `null`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 186 assertions, 0 failures`.

Focused delta: +1 focused TestRunner PASS case and +9 focused assertions.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-inferred-char-range-currentbase.php
```

The smoke emits `char_blocks_span_range_inferred=true`, `payload_excluded=true`, `inverted_character_order_rejected=true`, `visible_wordpress_text="Inferred span range"`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext link/ref preservation, ref integer validation, disable_links behavior, valid minimal kept-character dictionary defaults, character/font payload sanitation, fractional index rejection, normalized/off-page bbox scaling, span script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior.

The bounded behavior is only parent span character-range inference when kept pdftext character rows already provide valid first/last `char_idx` metadata.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
