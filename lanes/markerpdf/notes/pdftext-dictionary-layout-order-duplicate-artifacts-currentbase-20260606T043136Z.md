# markerpdf pdftext dictionary layout/order duplicate artifacts current-base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260606T043136Z`
Base: `947146f41795aca15ad85ab6bc2832fb18205413`

## Source truth

Upstream markerPDF trims the pdftext dictionary pages to the selected page range, renders one low-resolution image per selected page, and zips one Surya layout/order result per rendered page. In the native supplied-artifact path, two keyed artifacts that both claim the same selected page are therefore ambiguous. The port must not pick one by PHP array order.

## Implementation

- `PdfPageArtifactSelector` now treats equal-score page-marker matches as a tie and emits a missing artifact for that selected page instead of assigning the first matching duplicate.
- The selected pdftext page remains importable in source order when duplicate layout/order artifacts cannot be trusted.
- Duplicate layout/order payload strings remain excluded from returned WordPress import metadata.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
FAIL rejects duplicate matching supplied order artifacts before selected pdftext layout assignment
Expected source-order text; actual text was reordered by the first duplicate matching order artifact.
1 test files, 341 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php
1 test files, 361 assertions, 0 failures
```

Related layout/order family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDocumentExtractorTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php lanes/markerpdf/tests/LayoutOrdererTest.php
4 test files, 723 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-duplicate-artifacts-currentbase.php
```

The smoke emits `source_order_preserved=true`, `duplicate_payloads_excluded=true`, `layout_plan_present=false`, `order_plan_present=false`, `cover_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. This reuses the existing native PHP pdftext dictionary, supplied-document conversion, layout/order artifact selection, and Markdown finalization components. No Python, pypdfium, Surya, OCR, GPU/model execution, external PDF tools, or live services were run.

## Non-overlap

This does not repeat the accepted DCTDecode duplicate DecodeParms boundary, pdftext dictionary supplied-range slicing, duplicate selected pdftext page replay guard, normalized/named bbox handling, page marker alias handling, or non-finite marker/bbox guards. The new behavior is duplicate equally matching supplied artifacts for one selected page.

Root harness: not run - isolated micro-slice.
