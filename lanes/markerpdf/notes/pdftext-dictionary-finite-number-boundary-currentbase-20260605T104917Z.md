# markerPDF pdftext dictionary finite-number boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T104917Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T104917Z`
Base accepted HEAD: `7b9a9fbd060eac121e12806680e789f70e2f7618`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `sddai/markerPDF` `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF pages to `pdftext.extraction.dictionary_output(...)` before converting returned dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- The locked `pdftext` dictionary boundary models page, bbox, font, character, and reference geometry as ordinary finite PDFium/PDF numeric metadata before Marker conversion. This slice keeps the native supplied-dictionary adapter fail-closed when fixture or caller data contains PHP `INF`/`NAN` values that cannot be represented as valid PDF geometry.

## Change

`PdfTextDocumentExtractor` and `PdfTextBlockConverter` now reject non-finite numeric values before WordPress block rendering or review metadata output:

- page-level `bbox`, `width`, and `height`;
- block, line, span, and kept-character `bbox` values;
- span rotation and font `weight` / `size`;
- page-reference `bbox`, `coord`, and `dest_pos` geometry.

This prevents malformed supplied pdftext dictionaries from pushing `NaN` or `Infinity` into Gutenberg paragraph metadata, `char_blocks`, link/reference review rows, or layout-order geometry while preserving valid finite numeric pdftext dictionaries.

## Red First

Before the source change, the focused regression failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result:

```text
FAIL rejects non finite pdftext numeric dictionaries before WordPress rendering
Expected exception InvalidArgumentException was not thrown
1 test files, 113 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 117 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/PdfTextBlockConverterTest.php
```

Result: `3 test files, 446 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-finite-number-boundary-currentbase.php
```

The smoke emitted `valid_page_imported=true`, finite span/char bboxes, `reference_url="#page-44-1"`, `non_finite_cases_rejected=["page_bbox_infinity","span_bbox_nan","font_size_infinity","char_bbox_nan","ref_coord_infinity"]`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused TestRunner PASS case and +5 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`, plus 1 WordPress smoke. Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted pdftext link/ref preservation, ref integer validation, page-number validation, disable_links behavior, keep_chars minimal dictionary inference, character/font payload sanitation, character index validation, normalized/off-page bbox scaling, span script flags, page-source metadata, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, or runtime/model behavior. The bounded behavior is only fail-closed finite numeric validation for supplied pdftext dictionary geometry and metrics at the dictionary core boundary.

## Dependency Closure

No new support component is needed. This reuses native PHP supplied pdftext dictionary sanitation, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
