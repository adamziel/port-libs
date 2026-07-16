# markerPDF AcroForm Direct Widget Canonical Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260607T060759Z`

Base accepted HEAD: `45539ec04b8219d154701e97e362a3479d34ee84`

## Source Truth

- Upstream `sddai/markerPDF` delegates searchable-PDF form metadata extraction to PDF parser layers before OCR/layout/model stages.
- PDF dictionaries are unordered, and PDF name keys may use `#xx` escapes. Direct Widget dictionaries repeated in a page `/Annots` array and a field parent `/Kids` array must therefore be matched by decoded dictionary content, not by raw byte order.
- The no-GPU markerPDF scope keeps this as native parser/review metadata only: form values and detached decoy fields stay out of visible WordPress paragraphs, and no form action, model, Python, or external PDF tool executes.

## Implementation

- `PdfAcroFormExtractor::canonicalDictionaryComparisonBody()` now canonicalizes top-level dictionary entries by decoded key name and sorted order before comparing Widget dictionaries.
- Duplicate top-level keys keep the last value, matching the extractor's existing current-dictionary boundary behavior.
- Added `PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php` proving a page-owned direct Widget with ordinary `/Subtype` and `/Parent` keys is matched to the parent `/Kids` direct Widget whose keys are reordered and escaped as `/Sub#74ype` and `/Par#65nt`.
- Added `wordpress-pdf-acroform-fields-direct-widget-canonical-currentbase.php` as the WordPress smoke path.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL matches direct AcroForm Widget Kids dictionaries by decoded unordered dictionary content
Values are not identical
Expected: array (
  0 => 'canonical.widget',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS matches direct AcroForm Widget Kids dictionaries by decoded unordered dictionary content
1 test files, 29 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsPageWidgetParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBranchRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS promotes page owned AcroForm sibling widget branches without importing the parent root
PASS rejects direct widget AcroForm Fields entries when Parent Kids do not own the widget
PASS materializes direct AcroForm Fields and Kids dictionaries without promoting nested decoys
PASS materializes direct page Widget annotations before AcroForm field repair
PASS materializes direct AcroForm dictionaries inside indirect Fields and Kids arrays
PASS bounds page widget AcroForm parent repair by escaped Parent ownership before WordPress field review
5 test files, 274 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php
Focused test run: 57 selected test files (root lock skipped)
57 test files, 4141 assertions, 0 failures

php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsDirectWidgetCanonicalBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-widget-canonical-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-widget-canonical-currentbase.php

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-widget-canonical-currentbase.php
emits field_names=["canonical.widget"], decoded_name_key_match=true, unordered_dictionary_match=true, detached_decoy_excluded=true, field_values_review_only=true, visible_text="Visible AcroForm direct widget canonical body", executes_python_or_models=false, and executes_external_pdf_tools=false.

php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
exits 0
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted duplicate `/Kids`, duplicate `/Fields`, parent ownership, page-widget parent repair, direct page Widget materialization, direct field dictionary materialization, indirect array materialization, generation-exact field references, object-stream fields, XFA, signature, submit/reset, widget appearance/action, annotation, xref, stream-filter, metadata, or attachment behavior. The bounded behavior is specifically unordered decoded dictionary equivalence for direct Widget dictionaries used by AcroForm page annotation repair.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, PDF dictionary parser, name decoder, page annotation materializer, field-tree traversal, page-widget map, field hierarchy review, and WordPress smoke output. Full appearance rendering, JavaScript/form action execution, XFA layout/data binding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, pypdfium/PIL rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around remaining forms/widget review edges, annotations, fonts, CMaps, stream filters, xref repair, metadata, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
