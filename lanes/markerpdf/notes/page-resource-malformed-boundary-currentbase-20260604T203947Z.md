# Page Resource Malformed Boundary Current Base, 2026-06-04

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260604T203947Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF text page-by-page through `pdftext.extraction.dictionary_output()` before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page-tree `/Resources` is an inheritable page attribute only when omitted or explicitly null in this lane's accepted current-base behavior. A page that declares `/Resources` with an unresolved indirect reference or malformed non-dictionary operand has a broken local resource declaration, so WordPress import must fail closed instead of silently promoting ancestor fonts, XObjects, or marked-content properties.

## Change

- `PdfTextExtractor::pageResourceDictionaryBody()` now distinguishes three states: inherit, resolved, and blocked.
- Omitted and explicit `null` page `/Resources` values still climb the page-tree lineage.
- Declared but unresolved or malformed page `/Resources` values now stop resource inheritance and disable the single-font fallback for that page.
- Form XObject expansion no longer falls back to scanning the whole page object when page `/Resources` is blocked, so nested private `/PieceInfo` XObject decoys remain review-only.
- `PdfPagePropertyExtractor` reports `status=unresolved_or_malformed`, `resolved=false`, the declaring page object, and any declared resource object number for WordPress review metadata.
- Added a WordPress smoke proving parent page-tree Font, XObject, and Properties resources are not promoted when the leaf page declares `/Resources 99 0 R`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on unresolved page Resources references instead of inheriting parent resources
Expected: ['A', 'B']
Actual: ['Parent font resource leak', 'Parent actual resource leak', 'Parent form resource leak']
FAIL fails closed on malformed non-dictionary page Resources operands before parent font lookup
Expected: ['A']
Actual: ['Parent array resource leak']
1 test files, 2 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php
1 test files, 22 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
7 test files, 1103 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-page-resource-malformed-boundary-currentbase.php
emits resource_status=unresolved_or_malformed, resource_resolved=false, resource_owner_object=3, resource_object=99, parent_font_resource_promoted=false, parent_form_resource_promoted=false, parent_actual_resource_promoted=false, executes_python_or_models=false, and executes_external_pdf_tools=false.

php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/src/PdfPagePropertyExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfPagePropertyExtractor.php

php -l lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-malformed-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-malformed-boundary-currentbase.php

php -r "json_decode(file_get_contents('lanes/markerpdf/lane-status.json'), true, 512, JSON_THROW_ON_ERROR); echo 'lane-status json ok'.PHP_EOL;"
lane-status json ok

git diff --check -- lanes/markerpdf
passed with no output
```

## Status Delta

- Focused markerPDF PHP behavior tests move `1090 -> 1091 pass / 0 fail`.
- WordPress scenarios move `1090 -> 1091`.
- Mapped upstream denominator is unchanged; this is a deeper native page-resource inheritance boundary under the already mapped searchable-PDF text extraction behavior.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary value reader, page-tree lineage resolver, font-map resource lookup, Form XObject expansion, marked-content property replacement, page-boundary review metadata, and WordPress smoke rendering. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, and benchmark parity remains intentionally out of scope for this no-GPU slice.

## Non-Overlap

This does not repeat accepted parent page `/Resources` font inheritance, leaf `/Resources` override, top-level `/Resources null` inheritance, nested private `/Resources` exclusion, inherited page-level Form XObject lookup, legacy Form XObject omitted-`/Resources` fallback, page `/Contents` non-inheritance, page-boundary resource metadata, marked-content property basics, optional-content visibility, or nested Form local resource scoping. The new boundary is specifically declared but unresolved or malformed page `/Resources` operands failing closed instead of inheriting parent page-tree resources.
