# markerPDF pdftext dictionary layout/order document-page payload marker boundary

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260605T103106Z`

Accepted base: `17084c137d0018e6cf17e49bcac91c3e1cb47745`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/pdf/extract_text.py::get_text_blocks()` obtains selected pdftext dictionary pages before marker layout/order runs.
- `marker/layout/order.py::surya_order()` zips order predictions with those selected Marker pages, so adapter metadata is page identity while nested copied pdftext dictionaries are payload fallbacks.
- Native no-GPU scope reuses supplied pdftext/order dictionaries and does not run Surya, OCR/layout models, PDFium rendering, Python workers, or external PDF tools.

## Implemented behavior

- `LayoutOrderer` now treats nested `pdftext` dictionaries as fallback-only page-marker sources when sanitizing supplied order metadata.
- If normal adapter wrappers such as `metadata.document_page` carry page identity, stale nested `pdftext.page` markers are ignored for review metadata.
- Supplied order geometry and trusted document-page identity are preserved, while copied pdftext payload text remains excluded from visible WordPress text and order metadata.

## Red-first evidence

After adding the focused regression and before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rescales normalized supplied order boxes before pdftext dictionary layout assignment
FAIL keeps nested pdftext page payload markers out of trusted document-page order metadata
Stale nested pdftext.page must not be preserved beside trusted document_page.

1 test files, 15 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 333 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-document-page-payload-currentbase.php
```

The smoke emits `trusted_document_page_preserved=true`, `stale_pdftext_page_marker_excluded=true`, `nested_pdftext_payload_excluded=true`, `visible_columns_in_reading_order=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused delta: +1 focused PASS case and +1 WordPress smoke.

Root harness status: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses native PHP pdftext dictionary conversion, selected page-range handling, keyed artifact selection, layout-order overlap matching, Markdown merge, and the WordPress smoke path. Live `pdftext`, PDFium/pypdfium rendering, Surya/OCR/order/layout models, Texify, Torch/model execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-overlap

This does not repeat selected page-range slicing, sparse keyed artifact matching, nested adapter marker discovery, source-page aliases, list-valued markers, page-index collisions, conflicting identity rejection, duplicate-keyed artifact reuse prevention, payload dictionary exclusion, normalized/zero-area order geometry, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work. The bounded behavior is specifically excluding stale nested `pdftext.page` markers from sanitized order review metadata when trusted adapter `document_page` metadata is present.

## Next task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and table/equation handoffs.
