# markerpdf pdftext dictionary layout/order source-page alias boundary

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T072613Z`

Base accepted HEAD: `17dbfeadf12027c4877b7ae89d1c4dadc1683066`

## Source truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`:

- `marker/pdf/extract_text.py` obtains `dictionary_output(..., page_range=...)` for the selected PDF range, then enumerates selected dictionary pages into Marker pages.
- `marker/layout/order.py` zips ordering predictions with the selected pages and sorts blocks by rescaled order bboxes.

This native no-GPU slice keeps that boundary for supplied adapter artifacts that use `source_page` or `document_page` as exact pdftext page identity aliases.

## Behavior

- `PdfPageArtifactSelector` now recognizes `source_page` and `document_page` as exact page markers when selecting sparse full-document layout/order artifacts for the current pdftext page range.
- `LayoutAnnotator` and `LayoutOrderer` preserve those scalar aliases in sanitized layout/order review metadata while still dropping payload dictionaries.
- Stale cover artifacts with `source_page` no longer attach positionally to the selected page when the selected artifact is keyed by wrapped `document_page`.

## Evidence

Red-first before source fix:

`php tools/run-tests.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`

Result: `1 test files, 743 assertions, 1 failures`; the new source-page alias case failed because two artifacts were counted and the stale first artifact was assigned positionally.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php`

Result: `2 test files, 1009 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-source-page-alias-currentbase.php`

Result: emitted `First source-page alias column. Second source-page alias column.` with `source_page_alias_cover_excluded=true`, `document_page_alias_selected=true`, `visible_columns_in_reading_order=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native `pdf-text-dictionary-layout-order-boundary`, `PdfPageArtifactSelector`, `LayoutAnnotator`, and `LayoutOrderer` components. Live OCR, Surya layout/order models, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
