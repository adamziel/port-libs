# Page Resource Inheritance Current Base, 2026-06-04

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260604T231926Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts text page-by-page through `pdftext.extraction.dictionary_output()` and pypdfium text pages before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF resource dictionaries are the page/form resource lookup boundary; page `/Resources` is inherited from the nearest page-tree ancestor when omitted/null, and category dictionaries such as `/Font`, `/XObject`, and `/Properties` are direct entries of the effective resource dictionary, not nested private dictionaries. Adobe PDF Reference 1.3 documents page-tree inheritable attributes and content-stream resources: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf

## Change

- `PdfTextExtractor` now resolves `/Font`, `/XObject`, and `/Properties` categories as top-level entries of the effective page/form resource dictionary.
- Nested decoy dictionaries under a valid resource category, such as `/Properties << /Private << /Font ... /XObject ... >> >>`, can no longer override the current inherited top-level resource categories.
- The existing WordPress resource-inheritance smoke now includes nested private `/Font` and `/XObject` decoys while still emitting only current inherited Gutenberg paragraphs.

## Red-First Evidence

Before the source change, the new focused fixture selected nested private resource categories:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
PASS uses inherited page resources for legacy Form XObjects that omit Resources without merging explicit form resources
FAIL uses top-level inherited resource categories before nested decoy dictionaries
Expected: Current inherited font text, Current inherited form text
Actual: Private nested font leak, Private nested XObject leak
1 test files, 9 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 15 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
3 test files, 663 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-resource-inheritance-import.php
emits top_level_resource_categories_ignore_nested_decoys=true and the expected five Gutenberg paragraphs.
```

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page-tree effective resource resolver, top-level dictionary scanner, Form XObject expansion, font map resolver, marked-content property lookup, stream decoder, and WordPress smoke path. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, benchmark, and external rendering parity remains intentionally out of scope for this no-GPU slice.

## Non-Overlap

This does not repeat accepted page-tree resource inheritance, leaf `/Resources` override, malformed `/Resources` fail-closed handling, top-level page `/Resources null` inheritance, legacy Form XObject omitted-`/Resources` fallback, page `/Contents` non-inheritance, page-boundary metadata, or nested Form local resource scoping. The new boundary is specifically direct resource-category selection inside the already effective current page/form resource dictionary.
