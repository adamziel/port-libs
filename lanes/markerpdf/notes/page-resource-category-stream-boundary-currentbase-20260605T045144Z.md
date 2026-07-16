# Page Resource Category Stream Boundary Current Base

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T045144Z`

Base accepted HEAD: `91eb954aad0c0c2adbdab2c5485221f409cc00e7`

## Source Truth

- Upstream markerPDF delegates searchable PDF text extraction to the PDF parser/text layer before model stages. Native PHP therefore owns the parser boundary for page-tree resource lookup before WordPress paragraph rendering.
- PDF page `/Resources` is an inheritable page attribute whose effective value is a resource dictionary. Resource categories such as `/Font`, `/XObject`, and `/Properties` are dictionaries; a stream object cannot become a resource-category dictionary just because its stream dictionary contains resource-like keys.

## Behavior

- `PdfTextExtractor::resourceCategoryDictionaryBody()` now rejects indirect resource-category operands that resolve to stream objects.
- `PdfPagePropertyExtractor` applies the same category-level stream-object rejection before reporting resource names in page-boundary review metadata.
- Valid sibling resource categories remain usable: a malformed inherited `/Properties` category stream no longer supplies `/ActualText`, a malformed `/XObject` category stream no longer expands a Form XObject, and a malformed `/Font` category stream no longer supplies a ToUnicode CMap.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores inherited Properties category stream objects without losing valid font or form resources
Actual: ... 'Stream property actual leak' ...
FAIL ignores inherited XObject category stream objects without losing valid font or property resources
Actual: ... 'Stream XObject category form leak'
FAIL ignores inherited Font category stream objects without losing valid XObject resources
Actual: ... 'Stream category font leak' ...
1 test files, 3 assertions, 3 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php
1 test files, 30 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
8 test files, 465 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
2 test files, 1034 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-resource-category-stream-boundary-currentbase.php
```

The smoke emits `valid_sibling_resource_categories_preserved=true`, `category_stream_actualtext_promoted=false`, `category_stream_xobject_promoted=false`, `category_stream_font_promoted=false`, and `stream_payload_promoted=false`.

## Status Delta

- Focused markerPDF PHP behavior tests move `1434 -> 1437 pass / 0 fail` by adding three category-stream resource-boundary PASS cases.
- WordPress scenarios move `1359 -> 1360` with `wordpress-pdf-resource-category-stream-boundary-currentbase.php`.
- Mapped upstream denominator is unchanged; this is a deeper native parser boundary under the already mapped searchable-PDF page resource inheritance behavior.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, exact-generation object lookup, page-tree resource resolver, stream-object detector, content tokenizer, font/CMap text extraction, Form XObject expansion, marked-content replacement, page-boundary metadata extractor, and WordPress smoke renderer. Live OCR/model execution, pypdfium/pdftext execution, raster rendering, and exact upstream GPU/model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted whole page `/Resources` stream-object rejection, `/Resources null` inheritance, malformed page `/Resources` fail-closed behavior, generation-mismatched resource references, resource-entry generation filtering, escaped page-tree `/Type` names, nested private resource decoy exclusion, Form XObject omitted/null resource inheritance, Form-local `/Properties` scoping, image XObject review, stream-filter boundaries, or xref repair. The bounded behavior is only inherited resource category operands (`/Font`, `/XObject`, `/Properties`) that resolve to stream objects.
