# markerPDF pdftext dictionary layout/order bbox-list boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T110324Z`

Accepted base: `f6dbe30624ad0570d265873814a3f8256148d7bb`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` delegates searchable PDF text to `pdftext.extraction.dictionary_output(..., page_range=...)`, so the native path starts from already selected pdftext dictionary pages.
- `marker/convert.py::convert_single_pdf()` applies layout/order handoffs after selected-page trimming.
- `marker/layout/order.py::surya_order()` zips supplied ordering predictions with selected Marker pages; when a no-GPU supplied adapter serializes order geometry as a bare bbox list, the list sequence is the only ordering signal.

## Implemented Behavior

- `LayoutOrderer` now infers one-based `position` values for bare four-number order bbox rows.
- Dictionary order rows keep their existing explicit-position behavior; missing `position` in dictionary rows still remains unranked.
- The page `order` metadata stores normalized inferred positions and excludes raw adapter payloads before WordPress paragraph rendering.
- Added a WordPress smoke for selected pdftext dictionary pages whose supplied order rows are bare bbox lists.

## Red-First Evidence

After adding the focused regression and before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 26 assertions, 1 failures
```

The failing assertion showed both bare bbox rows were accepted but stored with `position => 0`, so their supplied order sequence could not be trusted.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rescales normalized supplied order boxes before pdftext dictionary layout assignment
PASS keeps nested pdftext page payload markers out of trusted document-page order metadata
PASS uses bbox-list order rows as ordered geometry before pdftext dictionary layout assignment

1 test files, 30 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bbox-list-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
3 test files, 834 assertions, 0 failures

php -l lanes/markerpdf/src/LayoutOrderer.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-bbox-list-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

The example emits `order_artifacts_trimmed=true`, `bbox_list_positions_inferred=true`, `supplied_sequence_preserved=true`, `cover_excluded=true`, `raw_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP pdftext dictionary converter, selected page artifact handling, layout ordering, supplied-document converter, Markdown finalization, and WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat selected-page artifact trimming, top-level keyed matching, wrapper-list markers, array marker normalization, duplicate-keyed no-replay, payload marker fallback, numeric-string bbox normalization, malformed/zero-area order row rejection, conflicting identity rejection, page-index collision handling, normalized order bbox scaling, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically bare bbox-list order rows preserving their supplied sequence for pdftext dictionary layout/order handoffs.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or table/equation handoff edges.
