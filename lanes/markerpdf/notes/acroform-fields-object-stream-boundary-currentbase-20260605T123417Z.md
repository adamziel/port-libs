# markerPDF AcroForm Fields Object-Stream Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T123417Z`

Base accepted HEAD: `d4106867af0d7368819042418c3d677c6a3c6f90`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF structure extraction to PDF parser layers before OCR/layout/model stages.
- PDF 1.5 object streams carry ordinary generation-zero indirect objects. Native AcroForm review must therefore make compressed field and Widget dictionaries available before walking catalog `/AcroForm /Fields` and page `/Annots`.
- The no-GPU markerPDF scope keeps this as parser/review metadata only: form values, alternate names, mapping names, and action-looking payloads must not become visible WordPress paragraph text or execute form actions.

## Implementation

- `PdfAcroFormExtractor::extractForm()` now expands missing direct `/ObjStm` dictionary members before catalog, field-tree, page-widget, action, XFA, and security review.
- Expansion is bounded:
  - reuses the existing native stream decoder for `/FlateDecode` and `/ASCIIHexDecode`;
  - requires valid `/N` and `/First` object-stream operands;
  - rejects malformed, duplicate, or trailing object-stream header pairs;
  - only adds missing generation-zero dictionary members;
  - never overrides a direct object selected by the normal object scanner;
  - skips top-level stream members.
- Added `PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php` proving compressed terminal fields and Widget annotations are recovered while a detached object-stream field decoy remains excluded.
- Added `wordpress-pdf-acroform-fields-object-stream-currentbase.php` as the WordPress smoke path.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL expands object-stream AcroForm field and widget dictionaries before WordPress review
Expected: ["compressed.email","compressed.status"]
Actual: []
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands object-stream AcroForm field and widget dictionaries before WordPress review
1 test files, 33 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*Test\.php$' | sort)
Focused test run: 11 selected test files (root lock skipped)
11 test files, 876 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(AcroForm|SecurityAcroForm).*Test\.php$' | sort) lanes/markerpdf/tests/PdfPageWidgetFieldActionLinkCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotationWidgetLinkCurrentBaseTest.php
Focused test run: 38 selected test files (root lock skipped)
38 test files, 3343 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-currentbase.php
field_count=2, object_stream_field_dictionaries_recovered=true, object_stream_widget_dictionaries_recovered=true, detached_object_stream_decoy_excluded=true, field_values_review_only=true, executes_python_or_models=false, executes_external_pdf_tools=false

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, direct Widget `/Fields` normalization, child branch/root normalization, token-aware array decoy exclusion, comment-split references, generation-exact field references, parent ownership, trailer-root selection, scalar/numeric/type operand generation matching, widget appearance/action/XFA/signature review, or xref/object-stream text/metadata/attachment extraction. The bounded behavior is only AcroForm field and Widget dictionaries stored as ordinary direct `/ObjStm` members before field review.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, dictionary parsing, stream decoding, field-tree traversal, page-widget mapping, and WordPress smoke output. Full xref-stream admission parity, appearance rendering, JavaScript/form action execution, XFA layout/data binding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior, preferably remaining AcroForm object-stream/xref generation boundaries, annotation action review edges, page geometry/resource handoff, stream-filter metadata, or security/attachment preflight behavior.
