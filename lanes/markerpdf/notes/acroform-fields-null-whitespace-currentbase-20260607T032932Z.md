# AcroForm Fields NUL Whitespace Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260607T032932Z`

Base accepted HEAD: `4f2a40d7ce52644b619c40dcb9423d278952be79`

## Behavior

PDF NUL bytes are now treated as PDF whitespace inside the native AcroForm parser token boundary. This covers catalog `/AcroForm` references, `/Fields` arrays, page `/Annots` widget references, field `/Kids`, widget `/Parent`, widget `/P`, widget `/Rect` numeric arrays, and indirect `/F` operands before WordPress form-field review.

The fixture also proves literal strings containing NUL-split reference-looking text stay decoys and do not become AcroForm fields. Field values, labels, and decoy strings remain review-only and do not leak into visible WordPress paragraphs.

## Evidence

Red-first before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNullWhitespaceBoundaryCurrentBaseTest.php
=> 1 test files, 1 assertions, 1 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNullWhitespaceBoundaryCurrentBaseTest.php
=> 1 test files, 34 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php
=> 32 test files, 1861 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-null-whitespace-currentbase.php
=> exits 0; field_count=2; field_names=["nullws.email","nullws.status"]; form_values_visible_in_text=false; executes_python_or_models=false; executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the existing native PHP PDF token reader in `PdfAcroFormExtractor`; no Python, CUDA, OCR, model execution, PDF action execution, or external PDF tools are introduced.

## Non-Overlap

This slice does not repeat prior AcroForm comment-reference, escaped-name, generation-boundary, indirect-array, object-stream, duplicate-key, parent-ownership, or page-widget repair work. It specifically maps ISO/PDF NUL-byte whitespace in AcroForm field and widget references, analogous to but separate from the accepted page-resource NUL-whitespace slice.

## Next

Continue no-GPU markerPDF work on native searchable-PDF behavior: fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
