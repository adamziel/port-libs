# markerPDF AcroForm Fields Stream-Object Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T021616Z`

Base accepted HEAD: `3d74120a9c1b8d588cf826b675c1e5e30d4592e7`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF structure extraction to PDF parser layers before OCR/layout/model stages.
- PDF AcroForm field trees are ordinary dictionaries rooted at catalog `/AcroForm /Fields`; Widget annotations are ordinary annotation dictionaries. Stream objects may carry appearance, XFA, JavaScript, or embedded-file payloads, but a malformed stream object referenced from `/Fields`, field `/Kids`, or page `/Annots` must not become an AcroForm field or Widget identity source.
- The no-GPU markerPDF scope keeps this as parser/review metadata only: field values, stream payload bytes, and form actions must not become visible WordPress paragraph text or execute actions.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stream objects as AcroForm field and widget dictionaries before WordPress review (lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'normal.field',
)
Actual: array (
  0 => 'stream.root.decoy',
  1 => 'normal.field',
  2 => 'stream.inline.decoy',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- Added an AcroForm-only stream-object identity guard in `PdfAcroFormExtractor`.
- Field roots, field ancestor lookup, field-name collection, field `/Kids` traversal, page-widget discovery, page-widget parent repair, and field-tree ownership helpers now reject direct stream objects before reading their leading stream dictionaries as field/widget dictionaries.
- Valid review-only stream paths remain available for appearance streams, XFA streams, JavaScript streams, embedded-file streams, object-stream field members, and page content streams.
- Added `PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php` for stream-carried field root, stream child Widget, and stream inline Widget decoys.
- Added `wordpress-pdf-acroform-fields-stream-object-boundary-currentbase.php` for the WordPress import smoke path.

## Verification

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-stream-object-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-stream-object-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stream objects as AcroForm field and widget dictionaries before WordPress review
1 test files, 24 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*Test\.php$' | sort)
Focused test run: 21 selected test files (root lock skipped)
21 test files, 1407 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(AcroForm|SecurityAcroForm).*Test\.php$' | sort) lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
Focused test run: 48 selected test files (root lock skipped)
48 test files, 3874 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-stream-object-boundary-currentbase.php
field_count=1, field_names=[normal.field], stream_root_field_excluded=true, stream_inline_widget_excluded=true, stream_child_widget_excluded=true, stream_payload_text_excluded=true, form_values_review_only=true, executes_form_actions=false, executes_javascript=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct Widget `/Fields` normalization, child branch/root normalization, token-aware array decoy exclusion, comment-split references, generation-exact references, parent ownership, trailer-root selection, scalar/numeric/type operand generation matching, direct dictionaries, indirect direct dictionaries, object-stream field expansion, widget appearance/action/XFA/signature review, submit/reset review, page widget link promotion, outline stream-root rejection, xref repair, stream filters, image metadata, OCR, or supplied table/equation handoffs. The bounded behavior is only rejecting direct stream objects as AcroForm field and Widget dictionaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary parser, field-tree traversal, page-widget map, stream-aware review paths, text extractor, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rendering, JavaScript/form action execution, XFA layout/data binding, signing/signature validation, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.

## Next

Continue with non-overlapping native searchable-PDF parser behavior, preferably inherited resources, fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
