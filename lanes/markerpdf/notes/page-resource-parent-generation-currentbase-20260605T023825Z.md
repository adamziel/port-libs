# markerPDF Page Resource Parent Generation Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260605T023825Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through page-scoped `pdftext.extraction.dictionary_output()`/PDFium text pages before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page resource inheritance follows the page tree through indirect `/Parent` references. Those references are generation-qualified, so `/Parent 2 1 R` must not silently inherit `/Resources` from a selected `2 0 obj` stale page-tree node.

## Change

- `PdfTextExtractor::pageObjectLineage()` now resolves page `/Parent` references through an exact generation check before following the parent object.
- Invalid parent lineage now blocks the single-global-font fallback, preventing stale parent `/Font` mappings from decoding page text when no effective page resources exist.
- `PdfPagePropertyExtractor::pageObjectLineage()` now applies the same generation check, keeping page-resource review metadata aligned with visible text extraction.
- Added a focused fixture where the catalog page tree lists page `3 0 R`, but the page claims `/Parent 2 1 R` while only `2 0 obj` contains stale `/Resources`. The native path now emits only raw fallback text `A` and excludes the stale Form XObject.
- Added `examples/wordpress-pdf-page-resource-parent-generation-currentbase.php` as the WordPress smoke.

## Red-First Evidence

Before the source change, after adding the focused fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on generation-mismatched page Parent references before stale resource inheritance
Expected: array (
  0 => 'A',
)
Actual: array (
  0 => 'Stale parent generation font leak',
  1 => 'Stale parent generation form leak',
)
1 test files, 69 assertions, 1 failures
```

An intermediate source change stopped stale Form expansion but still leaked the single global font map. The final patch blocks both paths.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 75 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 981 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-generation-currentbase.php
emits parent_generation_mismatch_blocks_inheritance=true, stale_parent_font_excluded=true, stale_parent_form_excluded=true, resource_review_empty=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, exact indirect-reference generation bookkeeping, page-tree lineage walker, resource dictionary resolver, Form XObject expansion, CMap/font maps, page-resource review metadata, and WordPress smoke path. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, benchmark, and external rendering parity remains intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, top-level page `/Parent` decoy parsing, inherited `/Resources null`, malformed `/Resources` fail-closed behavior, resource-entry generation filtering, escaped `/Type` page-tree names, page `/Contents` non-inheritance, nested Form local resource scoping, xref repair, object-stream repair, or image/filter/OCR/model work. The bounded behavior is only generation-exact page `/Parent` lineage before inherited resource lookup.
