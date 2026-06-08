# markerPDF AcroForm Direct Parent Field Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T184504Z`

Base accepted HEAD: `307a601051e9f25717d7e310792b824a3d11215f`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text and parser metadata through the PDF parser boundary before OCR/layout/model stages. Under the current no-GPU scope, this PHP lane owns native AcroForm field-tree review before WordPress import.

PDF field trees can store terminal fields as indirect objects while embedding their `/Parent` as a direct field dictionary. That compact encoding should preserve inherited parent field metadata only when the direct parent `/Kids` array owns the listed child. A detached direct-parent decoy must not rename the child or provide inherited `/FT`, `/DV`, `/DA`, `/MaxLen`, `/TU`, or `/TM` review metadata.

## Red-First Evidence

Before the patch, the focused current-base test failed because the listed child field imported as a local name instead of using the owned direct parent hierarchy:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentFieldBoundaryCurrentBaseTest.php
FAIL materializes direct AcroForm Parent field dictionaries only when Kids owns the listed child
Expected: profile.email, local.only
Actual: email, local.only
1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfAcroFormExtractor` now detects a top-level direct `/Parent` dictionary on a listed field object before the normal hierarchy walk.
- The direct parent is materialized as a synthetic generation-zero object only when it is an AcroForm field dictionary, not a widget, and its `/Kids` array references the child object.
- The child object's direct `/Parent << ... >>` operand is rewritten in memory to the synthetic `N 0 R`, allowing existing inherited-name, type, value, default-appearance, and max-length review logic to run unchanged.
- Direct parent dictionaries whose `/Kids` arrays do not own the child remain local decoys and do not leak into field review or visible text.

## Verification

Changed PHP files were syntax checked:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentFieldBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-field-currentbase.php
```

All reported no syntax errors.

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentFieldBoundaryCurrentBaseTest.php
1 test files, 54 assertions, 0 failures
```

Adjacent direct-parent/materialization family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentFieldBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsParentOwnershipBoundaryCurrentBaseTest.php
6 test files, 313 assertions, 0 failures
```

Full AcroForm current-base family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*CurrentBaseTest.php
88 test files, 4799 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-field-currentbase.php
```

The smoke exits 0 and emits `direct_parent_inherited_name_preserved=true`, `owned_direct_parent_metadata_inherited=true`, `unowned_direct_parent_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm direct root dictionaries, direct field dictionaries in `/Kids`, direct widget entries in `/Fields`, direct widget parents without `/Kids`, indirect direct dictionary operands, parent ownership for indirect parent objects, non-field parent rejection, duplicate-key boundaries, token-aware `/Fields` parsing, escaped reference operands, action review, XFA/signature review, appearance execution, or visible text extraction. The bounded behavior is specifically owned direct `/Parent` field dictionaries attached to listed child fields.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary and array parser, synthetic direct-dictionary materialization, AcroForm field hierarchy walker, page/widget review, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/pypdfium execution, Streamlit/FastAPI workers, Poppler, Ghostscript, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
