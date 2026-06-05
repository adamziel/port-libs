# markerPDF Page Resource Parent Boundary Current Base

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260604T235046Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts text page-by-page through `pdftext.extraction.dictionary_output()` and pypdfium text pages before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page-tree resource inheritance is keyed by top-level page dictionary entries. Nested private dictionaries such as `/PieceInfo` review metadata must not redirect `/Parent` lineage or inherited `/Resources` lookup.

## Change

- `PdfTextExtractor::pageObjectLineage()` now resolves `/Parent` from the page dictionary's top-level value instead of a raw first-match regex.
- `PdfPagePropertyExtractor::dictionaryRawValue()` now returns top-level dictionary entries through the existing dictionary token reader, keeping page-resource review metadata aligned with visible text extraction.
- Added a focused fixture where a nested private `/Parent 99 0 R` decoy appears before the real top-level `/Parent 2 0 R`. The extractor now uses object `2` and resource object `10`, excluding the decoy font and Form XObject.
- Added `examples/wordpress-pdf-page-resource-parent-boundary-currentbase.php` as the WordPress smoke.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses inherited page resources for legacy Form XObjects that omit Resources without merging explicit form resources
PASS uses top-level inherited resource categories before nested decoy dictionaries
FAIL uses top-level page Parent before nested decoy Parent keys for inherited resources
Expected: Top level parent font text, Top level parent form text
Actual: Nested decoy parent font leak, Nested decoy parent form leak
1 test files, 16 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 28 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 969 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-parent-boundary-currentbase.php
emits top_level_parent_selected=true, nested_parent_decoy_excluded=true, inherited_form_selected=true, resource_owner_object=2, resource_object=10, and no Python/model/external PDF tool execution.
```

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object parser, dictionary token reader, page-tree lineage walker, resource dictionary resolver, Form XObject expansion, CMap/font maps, page-resource review metadata, and WordPress smoke path. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, benchmark, and external rendering parity remains intentionally out of scope for this no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, legacy Form XObject omitted-`/Resources` fallback, top-level resource category selection, malformed `/Resources` fail-closed behavior, `/Resources null` inheritance, page `/Contents` non-inheritance, page-box inheritance, page-tree cycle guards, xref repair, object-stream repair, or font/CMap width slices. The new boundary is specifically top-level `/Parent` selection for page-resource inheritance when nested private dictionaries contain decoy `/Parent` keys.
