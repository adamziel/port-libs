# Page Resource Inheritance Current Base, 2026-06-03

Micro-slice: `markerpdf-page-resource-inheritance-current-base-20260603T084923Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts PDF text page-by-page through `pdftext.extraction.dictionary_output()` and pypdfium text pages before Marker block conversion: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- Adobe PDF Reference 1.3 describes page content streams as using the page resource dictionary, and notes that a Form XObject may omit `/Resources`, in which case resources are looked up from the page where the form is used: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf

## Change

- `PdfTextExtractor::expandFormXObjectInvocations()` now computes an effective resource owner for each Form XObject before resolving nested XObjects, font maps, and optional-content properties.
- A Form XObject that omits a top-level `/Resources` entry falls back to the invoking page/form resource scope, matching legacy PDF behavior.
- A Form XObject with an explicit `/Resources` entry still keeps that local resource scope and does not merge inherited page XObjects.
- The WordPress resource-inheritance smoke now emits a legacy omitted-`/Resources` nested Form paragraph and confirms explicit Form resources remain unmerged.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
FAIL uses inherited page resources for legacy Form XObjects that omit Resources without merging explicit form resources
Expected: array (
  0 => 'Legacy nested form inherited resources',
  1 => 'Explicit form local resources',
)
Actual: array (
  0 => 'Explicit form local resources',
)
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php
1 test files, 8 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsResourcesTransitionLabelCurrentBaseTest.php
5 test files, 754 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-resource-inheritance-import.php
emits Inherited One, Inherited Form One, Legacy Nested Form Resources, Explicit Form Local Resources, and Inherited Two with native-only smoke flags.
```

## Status Delta

- Focused markerPDF PHP behavior tests move `992 -> 993 pass / 0 fail`.
- WordPress scenarios move `992 -> 993`.
- Mapped upstream denominator is unchanged; this is a deeper native PDF resource-scope boundary under the already mapped page-resource inheritance behavior.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, page-tree effective resource resolver, Form XObject expansion path, font map resolver, optional-content property filter, and WordPress smoke rendering. Full upstream Python/pdftext/pypdfium, OCR/model, table, equation, Streamlit/FastAPI, and benchmark parity remains intentionally out of scope for this no-GPU slice.

## Non-Overlap

This does not repeat accepted parent page `/Resources` font inheritance, leaf `/Resources` override, inherited page-level Form XObject lookup, marked-content property inheritance, optional-content basics, page `/Contents` non-inheritance, page-boundary resource metadata, or nested Form local resource scoping. The new boundary is specifically legacy Form XObjects that omit top-level `/Resources` and therefore resolve nested XObjects/fonts through the invoking page resource scope while explicit Form resources remain isolated.
