# markerPDF AcroForm Fields Catalog Reference Tail Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T180754Z`

Base accepted HEAD: `088647638f8d8cae6935e8550e20545d341fc5dc`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable PDF parser output before OCR/layout/model stages. In the native no-GPU PHP lane, catalog `/AcroForm` selection must therefore be a token-safe PDF object boundary before WordPress field/widget review is assembled.

PDF dictionary values are one object. A catalog entry such as `/AcroForm 5 0 R 50 0 R /Lang (...)` is malformed because the extra top-level reference appears before the next catalog key. The native parser now fails that AcroForm root closed instead of trusting the first reference token, while `/AcroForm 5 0 R % comment\n/Lang (...)` remains usable.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCatalogReferenceTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects catalog AcroForm indirect references with trailing operands before field review (lanes/markerpdf/tests/PdfAcroFormFieldsCatalogReferenceTailBoundaryCurrentBaseTest.php)
Values are not identical
Expected: false
Actual: true
PASS keeps catalog AcroForm indirect references with comment-only tails usable

1 test files, 17 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor::acroFormDictionaryBody()` now applies `topLevelValueSpanHasTrailingOperand()` before resolving either direct or indirect catalog `/AcroForm` values.
- Tailed catalog AcroForm references return the empty form boundary before field repair, page widget repair, calculation-order review, XFA review, or action review.
- Comment-only tails before the next catalog key still skip as PDF whitespace and preserve AcroForm field metadata.

## Focused Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsCatalogReferenceTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects catalog AcroForm indirect references with trailing operands before field review
PASS keeps catalog AcroForm indirect references with comment-only tails usable

1 test files, 33 assertions, 0 failures
```

AcroForm field family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroFormFields*CurrentBaseTest.php' | sort)
Focused test run: 64 selected test files (root lock skipped)
64 test files, 3280 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-catalog-reference-tail-currentbase.php
```

The smoke exits `0` and emits `tailed_catalog_reference_excluded=true`, `comment_only_catalog_reference_preserved=true`, `field_values_review_only=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary token scanner, comment-aware whitespace skipper, AcroForm field hierarchy repair, page Widget metadata collection, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, form action execution, JavaScript execution, raster rendering, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted AcroForm root dictionary object tails, direct catalog AcroForm dictionary tails, direct or indirect `/Fields` and `/Kids` array tails, scalar object tails, indirect reference-wrapper operands, field-tree cycles, duplicate keys, parent ownership, page-widget repair, xref generation selection, object-stream fields, XFA/signature/action review, or submit/reset/default-resource appearance metadata. The bounded behavior is only catalog-level `/AcroForm` indirect reference values tailed by an extra top-level operand before the next catalog key.
