# Table OCR Structure Assignment Regression

Slice: `table-ocr-structure-assignment-regression-currentbase`
Session: `port-dev-markerpdf-table63-20260602T213558Z`
Base accepted HEAD: `99591cbc6337f72bc79127211674461d42c783cc`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes table crops and page text lines through `marker/tables/table.py` into the locked table helper.
- Locked `tabled-pdf==0.1.4` `tabled/inference/recognition.py::recognize_tables` runs OCR for detector cells, zips `ocr_pred.text_lines` back onto the same `table_cells`, then calls `batch_table_recognition(table_imgs, table_cells, ...)`.
- Locked `tabled-pdf==0.1.4` `tabled/assignment.py::assign_rows_columns` assigns the text-bearing table cells to recognized rows and columns before Markdown formatting.

## Behavior

The supplied PHP boundary now preserves OCR detector-cell text when supplied table-recognition output returns blank structure cells that are reordered or rebuilt by the recognition stage. Blank structure cells inherit text by bbox overlap/center geometry first, then fall back to same-index text only when geometry is unavailable. Explicit model text remains authoritative.

This fixes a current-base regression where a forced-OCR table could render as an empty Gutenberg table if the supplied `recognized_tables[*].cells` array carried row/column structure but no text and did not share detector-cell order.

## Evidence

Red before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 675 assertions, 2 failures
FAIL preserves forced OCR text when supplied table structure cells are reordered
FAIL preserves OCR text by geometry when recognition returns reordered structure cells
```

Green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/TableRecognizerTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
2 test files, 694 assertions, 0 failures
```

Local WordPress smoke added:

```text
php lanes/markerpdf/examples/wordpress-table-ocr-structure-assignment-regression-currentbase.php
```

Expected smoke signals include `structure_cells_reordered=true`, `rendered_table_order_preserved=true`, `excluded_stale_pdftext_table_line=true`, and no Python/model/external PDF execution.

## Non-Overlap

This does not repeat the accepted table OCR layout text-line extraction, OCR polygon assignment, OCR grid-border conflict review, merged-cell geometry, multiline header folding, spanning grid accessibility, or table section/caption review slices. The new behavior is specifically OCR detector text surviving blank/reordered table-recognition structure cells before row/column assignment.

## Dependency Closure

No new support component is needed. This reuses the native supplied-document converter, table recognizer, row/column assignment, OCR text-line supplied boundary, and table formatting path. Full live upstream parity remains dependency-gated on pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout models, tabled neural recognition, PIL rendering, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
