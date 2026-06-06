# markerpdf page resource duplicate category current-base

Session: `port-dev-markerpdf-resource-inherit-20260606T233823Z`
Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260606T233823Z`
Accepted base: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`

## Source-truth boundary

The upstream markerPDF searchable-PDF path delegates native PDF text and
resource handling before any OCR/model fallback. Under the current no-GPU
markerPDF scope, this slice stays in the native parser/converter layer:
effective page resource dictionaries that repeat a top-level category such as
`/Font`, `/XObject`, or `/Properties` must use the current top-level category
value before inherited text extraction, marked-content `/ActualText`, and Form
XObject lookup. Earlier duplicate category dictionaries are stale decoys and
must not leak into WordPress paragraphs.

## Implementation

- `PdfTextExtractor::resourceCategoryDictionaryBody()` now gathers all
  top-level values for the requested resource category and resolves the last
  current value.
- The behavior applies to all category names that use this resolver, including
  `/Font`, `/XObject`, `/Properties`, `/ColorSpace`, `/ExtGState`, `/Pattern`,
  and `/Shading`.
- The existing page boundary metadata already reports current category names;
  the text, marked-content, and form lookup path now matches that current
  dictionary behavior.

## Red-first evidence

Before the source fix, the focused test failed with stale category output:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateCategoryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses current duplicate resource categories before inherited page text and form lookup
Expected: [
  'Current duplicate category font text',
  'Current duplicate category ActualText',
  'Current duplicate category form text',
]
Actual: [
  'Stale duplicate category font leak',
  'Stale duplicate category ActualText leak',
  'Stale duplicate category form leak',
  'Stale duplicate category form leak',
]
1 test files, 1 assertions, 1 failures
```

## Passing evidence

Status delta: `phpPass` 2712 -> 2713, `wordpressScenarios` 2284 -> 2285,
`pdfPageResourceInheritanceCurrentBaseBehaviors` 1 -> 2, and
`mappedPdfPageResourceInheritanceCurrentBaseBehaviors` 1 -> 2.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceDuplicateCategoryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current duplicate resource categories before inherited page text and form lookup

1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 29 selected test files (root lock skipped)
29 test files, 975 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-duplicate-category-currentbase.php
```

The WordPress smoke emits current duplicate category paragraph text and reports
`current_font_category_selected=true`, `current_xobject_category_selected=true`,
`current_properties_category_selected=true`, `stale_category_text_excluded=true`,
`stale_category_resource_name_excluded=true`,
`raw_actual_glyph_excluded=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Non-overlap

This is not the accepted duplicate top-level `/Resources` key slice, page-tree
inheritance baseline, direct/indirect null resource behavior, malformed
`/Resources` dictionary handling, resource entry wrapper behavior, generation
filtering, category stream fail-closed behavior, ExtGState font array behavior,
Form XObject null-resource inheritance, xref repair, metadata, annotations,
forms, image filters, or OCR/model parity. The new boundary is duplicate
resource-category dictionaries inside the already selected effective page
resource dictionary.

## Dependency closure

No new support component is needed. The slice reuses the native PHP PDF object
scanner, top-level dictionary value parser, page resource lineage resolver,
resource category resolver, text extractor, marked-content handler, Form
XObject executor, page boundary metadata extractor, and WordPress smoke path.
Live OCR, Surya/Texify/Torch/model execution, PDFium runtime parity, and
external PDF tools remain intentionally out of scope for this no-GPU markerPDF
lane.

Next task: continue non-overlapping native markerPDF work around searchable-PDF
fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations,
forms, page geometry, image/filter metadata, or supplied-boundary table/equation
handoffs.

Root harness: not run - isolated micro-slice.
