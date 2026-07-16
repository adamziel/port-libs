# markerPDF AcroForm Fields Duplicate-Key Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T161124Z`

Base accepted HEAD: `d8c5378cfd71f3d3e903f3a54d8aa0ca34a9c783`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF structure extraction to PDF parser layers before OCR/layout/model stages.
- PDF dictionaries are name-to-value maps; a malformed AcroForm dictionary with repeated top-level `/Fields` keys must not merge stale field arrays into current review metadata.
- The no-GPU markerPDF scope keeps this as parser/review metadata only: form values, stale field labels, and literal `/Fields` text stay out of visible WordPress paragraphs and no form action/model/external PDF tool executes.

## Implementation

- `PdfAcroFormExtractor::fieldReferencesFromAcroForm()` now uses the last top-level `/Fields` value when resolving the AcroForm root field array.
- Added a bounded token-aware helper that scans only top-level dictionary entries, decodes escaped names such as `/Fie#6Cds`, skips literal strings, arrays, nested dictionaries, hex strings, and comments, and returns the final matching value.
- Existing dictionary lookups remain unchanged; the new last-value behavior is limited to the AcroForm root `/Fields` field-array boundary.
- Added `PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php` proving a stale first `/Fields` array and a literal `/Fields` decoy do not surface while the later escaped `/Fie#6Cds` array supplies the current field.
- Added `wordpress-pdf-acroform-fields-duplicate-key-boundary-currentbase.php` as the WordPress smoke path.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last top-level AcroForm Fields key before WordPress field review
Expected: ["article.current_duplicate"]
Actual: ["stale.duplicate_fields","article.current_duplicate"]
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-key-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-key-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last top-level AcroForm Fields key before WordPress field review
1 test files, 27 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*Test\.php$' | sort)
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1018 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-key-boundary-currentbase.php
emits field_names=["article.current_duplicate"], field_value_review_only=true, stale_duplicate_field_imported=false, literal_fields_decoy_imported=false, visible_text_imported=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm escaped `/Fie#6Cds` key parsing, token-aware `/Fields` array filtering, indirect field arrays, comment-split references, generation-exact field references, child branch/root normalization, page-widget repair, direct Widget `/Fields` normalization, parent ownership, object-stream field expansion, widget appearance/action/XFA/signature review, or xref/trailer-root selection. The bounded behavior is only repeated top-level AcroForm `/Fields` keys where the later current field array must replace, not merge with, a stale earlier field array.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, dictionary parsing, AcroForm field-tree traversal, page-widget mapping, and WordPress smoke output. Full appearance rendering, JavaScript/form action execution, XFA layout/data binding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior, preferably remaining AcroForm action/value boundaries, annotations/forms, page geometry/resource handoff, xref/object-stream repair, stream-filter metadata, security preflight, or attachment review behavior.
