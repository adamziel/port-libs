# markerPDF pdftext dictionary source-bbox boundary current base

Micro-slice: `markerpdf-pdftext-dictionary-core-boundary-current-base-20260605T200237Z`
Session: `port-dev-markerpdf-pdftext-dictionary-20260605T200237Z`
Base accepted HEAD: `f6885b895654ed57b2bef7472612e1769d19f2be`

## Source Truth

- Upstream markerPDF remains pinned at manifest commit `sddai/markerPDF` `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable-PDF text to `pdftext.extraction.dictionary_output(...)` before converting dictionaries into Marker page/block/span structures: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- `pdftext.extraction.dictionary_output()` receives pages from PDFium-backed `get_pages()`, processes page dictionaries, and for 90/270 degree pages swaps width/height and reverses the source page bbox after child bboxes are processed: https://raw.githubusercontent.com/datalab-to/pdftext/master/pdftext/extraction.py

## Change

`PdfTextBlockConverter` now rejects supplied pdftext dictionary pages whose source `bbox` has zero absolute width or zero absolute height before emitting Marker page geometry, `pdftext_source` metadata, char blocks, or WordPress paragraphs.

Valid reversed bboxes are still accepted. This preserves the rotated `dictionary_output()` boundary where pdftext may return `[x2, y2, x1, y1]` for 90/270 degree pages, while blocking degenerate adapter dictionaries that cannot come from a real page extent.

## Red First

Before the source change, the new focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result: `1 test files, 187 assertions, 1 failures`.

The failed case expected `InvalidArgumentException` for zero-width and zero-height source page bboxes, but the current base converted them into zero-size WordPress page geometry.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryCoreBoundaryCurrentBaseTest.php
```

Result after fix: `1 test files, 190 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-source-dimension-boundary-currentbase.php
```

The smoke emitted `zero_bbox_width_rejected=true`, `zero_bbox_height_rejected=true`, `zero_width_rejected=true`, `negative_height_rejected=true`, `positive_dimensions_preserved=true`, `visible_text_imported=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused TestRunner PASS case and +4 focused assertions in `PdfTextDictionaryCoreBoundaryCurrentBaseTest.php`; the existing WordPress source-dimension smoke was updated in place. Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted nonpositive `width`/`height` metadata rejection, finite-number validation, page integer validation, rotated normalized bbox scaling, off-page normalized bbox scaling, link/ref preservation, disable-links behavior, keep-chars sanitation, character index validation, font flag validation, script flags, sorting, blank-page preservation, layout/order artifact alignment, parser/xref repair, font/CMap/native PDF extraction, image/filter review, annotations/forms/security preflight, table/equation supplied boundaries, runtime behavior, or model/OCR paths.

The bounded behavior is only zero-width/zero-height source page `bbox` rejection before WordPress-facing pdftext dictionary output.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied pdftext dictionary converter, block conversion, Markdown/WordPress smoke rendering, and focused PHP tests. Live `pdftext`, pypdfium/PDFium rendering, Surya/OCR/layout/order/table-cell models, Texify, Torch/model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane rule.
