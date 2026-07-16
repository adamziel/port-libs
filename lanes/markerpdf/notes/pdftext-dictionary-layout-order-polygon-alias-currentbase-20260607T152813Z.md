# markerpdf pdftext dictionary layout/order polygon aliases current-base

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260607T152813Z`

Accepted base: `6a3ea0f4861660790e73a0b7403add52995f31fa`

## Source Truth

- Upstream markerPDF extracts selected `pdftext.dictionary_output` pages before applying supplied layout and Surya ordering results.
- The native no-GPU boundary reuses supplied layout/order JSON and does not run Surya, OCR, Torch, Python, raster rendering, or external PDF tools.
- Existing table-boundary handoffs already normalize serialized four-corner geometry aliases (`points`, `vertices`, `quad`, `quadrilateral`, `quadrilateral_points`) into bbox geometry. This slice applies the same bounded geometry contract to supplied layout/order rows before WordPress import.

## Change

- `LayoutAnnotator` and `LayoutOrderer` now reduce four-corner geometry aliases to bbox rows before block-type annotation and reading-order assignment.
- Flat eight-number quads are accepted for alias rows as well as four point objects/lists.
- Raw adapter payload fields remain excluded from downstream review metadata.

## Red-First Evidence

Before the implementation patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
2 failures
```

Failures showed alias order rows were ignored, leaving source-ordered columns, and the WordPress supplied converter did not promote the alias-backed title to a heading.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php
1 test files, 26 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderPolygonAliasBoundaryCurrentBaseTest.php
2 test files, 820 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-polygon-alias-currentbase.php
exits 0; layout_polygon_aliases_assigned=true; order_polygon_aliases_assigned=true; raw_payload_excluded=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native supplied-document converter, layout annotator, and layout orderer. GPU/model execution remains intentionally out of scope for markerPDF.
