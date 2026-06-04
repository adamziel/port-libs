# Page Resource Top-Level Boundary Current Base, 2026-06-04

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260604T112704Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts searchable PDF text page-by-page through `pdftext.extraction.dictionary_output()` before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF page-tree resources are inheritable page attributes. A top-level page `/Resources null` behaves like an omitted value and inherits from the nearest page-tree ancestor, while nested private dictionaries such as `/PieceInfo ... /Private ... /Resources` are review metadata and not page resource dictionaries.

## Change

- `PdfTextExtractor::resourceDictionaryBody()` now resolves only a top-level `/Resources` value using the existing top-level PDF value reader.
- Explicit top-level `/Resources null` now continues page-tree inheritance instead of allowing a later nested `/Resources` dictionary to win.
- `PdfPagePropertyExtractor` now reports resource review metadata from the same top-level dictionary entries, so WordPress page-boundary review records the inherited `/Pages` resource owner.
- Added a WordPress smoke that renders parent-inherited text, parent-inherited Form XObject text, and parent `/Properties` ActualText while proving nested PieceInfo private resources are not promoted.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php
FAIL inherits parent page resources when page top-level Resources is null despite nested private Resources
Expected:
  Parent inherited resources text
  Parent inherited form text
  Parent actual resource text
Actual:
  Private PieceInfo resource leak
  Private PieceInfo form leak
  Private PieceInfo actual leak
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php
1 test files, 13 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
6 test files, 1013 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-resource-top-level-boundary-currentbase.php
emits Parent inherited resources text, Parent inherited form text, Parent actual resource text, resource_owner_object=2, resource_object=10, and private_pieceinfo_resources_promoted=false.
```

## Status Delta

- Focused markerPDF PHP behavior tests move `1050 -> 1051 pass / 0 fail`.
- WordPress scenarios move `1050 -> 1051`.
- Mapped upstream denominator is unchanged; this is a deeper native page-resource inheritance boundary under the already mapped searchable-PDF text extraction behavior.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, top-level dictionary value reader, page-tree lineage resolver, Form XObject expansion, marked-content property replacement, page-boundary review metadata, and WordPress smoke rendering. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, and benchmark parity remains intentionally out of scope for this no-GPU slice.

## Non-Overlap

This does not repeat accepted parent page `/Resources` font inheritance, leaf `/Resources` override, inherited page-level Form XObject lookup, legacy Form XObject omitted-`/Resources` fallback, page `/Contents` non-inheritance, page-boundary resource metadata, marked-content property basics, optional-content visibility, or nested Form local resource scoping. The new boundary is specifically top-level page `/Resources null` inheriting parent page-tree resources while nested private `/Resources` dictionaries remain review-only.
