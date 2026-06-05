# markerPDF pdftext dictionary source-dimension boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T161526Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T161526Z`
Base accepted HEAD: `095be0d2eedc7a57eaec04ab0f0f36c36493d12a`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `sddai/markerPDF` `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF pages to `pdftext.extraction.dictionary_output(...)` before converting returned dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.pdf.pages.get_pages()` derives page `width` and `height` from absolute page bbox extents before `dictionary_output()` hands page dictionaries to markerPDF: https://raw.githubusercontent.com/VikParuchuri/pdftext/master/pdftext/pdf/pages.py

## Change

`PdfTextBlockConverter` now rejects supplied pdftext page `width` and `height` values when they are zero or negative before emitting WordPress-facing `pdftext_source` metadata.

Valid positive dimensions still pass through as review metadata, and rendered Marker page dimensions remain derived from the page bbox as before. This keeps malformed adapter dictionaries from publishing impossible source-page dimensions into Gutenberg review metadata while preserving the native no-GPU pdftext dictionary boundary.

## Red First

Before the source change, a supplied page with `width => -400.0` and `height => 0.0` converted successfully and emitted:

```text
pdftext_source.width = -400.0
pdftext_source.height = 0.0
```

The new focused test locks that boundary with four rejected nonpositive dimension rows and one valid positive-dimension row.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextBlockConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-dimension-boundary-currentbase.php
```

Result: all three syntax checks passed.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 172 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php
```

Result: `3 test files, 504 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-dimension-boundary-currentbase.php
```

The smoke emitted `positive_dimensions_preserved=true`, `zero_width_rejected=true`, `negative_height_rejected=true`, `visible_text_imported=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused TestRunner PASS case and +6 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`, plus 1 WordPress smoke. Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext finite-number validation, page integer validation, page-source geometry preservation, normalized/off-page bbox scaling, rotated normalized bbox scaling, link/ref preservation, disable-links behavior, keep-chars sanitation, character index validation, font flag validation, script flags, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, runtime behavior, or model/OCR paths. The bounded behavior is only zero/negative page `width`/`height` rejection before `pdftext_source` metadata output.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary converter, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
