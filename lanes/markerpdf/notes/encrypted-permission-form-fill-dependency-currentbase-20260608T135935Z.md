# Encrypted Permission Form-Fill Dependency Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T135935Z`

Base: `df943add6ecdc665f4d2de6ef6093bc35935d6e0`

## Source Truth

- Upstream markerPDF opens and inspects PDFs through pypdfium/PDFium boundaries; this native lane stays no-GPU and no-decryption, so encrypted documents are summarized for WordPress review instead of being decrypted or permission-enforced.
- Adobe PDF Reference 1.7 / ISO 32000 permission semantics: Standard security-handler bit 6 allows adding/modifying annotations and filling interactive form fields; revision 3+ bit 9 separately allows filling existing form fields even when bit 6 is clear.

## Behavior

The native Standard permission preflight now treats `add_or_modify_annotations` permission as sufficient for `fill_form_fields` review when the narrower form-fill bit 9 is clear. The operation row records:

- `bit_set=false` for `fill_form_fields`;
- `granted_by_permission_name=add_or_modify_annotations`;
- `dependency_status=allowed_by_add_or_modify_annotations_permission`;
- `effective_status=allowed_by_permission_bit_pending_authentication`.

The import still blocks encrypted visible text and keeps owner/user validation bytes out of metadata JSON until password validation and decryption exist.

## Evidence

Red-first before source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats annotation permission bit as sufficient for existing form-fill review
Expected allowed permissions included fill_form_fields; actual allowed permissions omitted it.
PASS keeps form-fill dependency encrypted payloads out of visible WordPress text
1 test files, 18 assertions, 1 failures
```

After source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats annotation permission bit as sufficient for existing form-fill review
PASS keeps form-fill dependency encrypted payloads out of visible WordPress text
1 test files, 55 assertions, 0 failures
```

Broader family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php
Focused test run: 66 selected test files (root lock skipped)
...
66 test files, 6184 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-form-fill-dependency-currentbase.php
exit 0; encrypted_text_blocked=true; permission_hex=FFFFF0F0;
form_fill_bit_set=false; form_fill_effective_status=allowed_by_permission_bit_pending_authentication;
form_fill_granted_by_permission_name=add_or_modify_annotations;
raw_auth_material_exposed=false; executes_decryption=false; executes_permission_enforcement=false;
executes_python_or_models=false; executes_external_pdf_tools=false
```

Final required checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/src/PdfSecurityPreflight.php
No syntax errors detected in lanes/markerpdf/src/PdfSecurityPreflight.php

php -l lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-form-fill-dependency-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-encrypted-form-fill-dependency-currentbase.php

git diff --check -- lanes/markerpdf
exit 0

php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintDependencyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionReservedBitsCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 333 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. This reuses the existing native `PdfMetadataExtractor`, `PdfSecurityPreflight`, and encrypted-text blocking path. Remaining GPU/model OCR, PDFium rendering, password validation, decryption, and permission enforcement remain intentionally out of scope for markerPDF no-GPU workers.

## Non-Overlap

This avoids the accepted crypt-filter, duplicate permission word, auth material readiness, reserved-bit, high-quality print dependency, public-key recipient, DSS/signature, image, xref, and runtime preflight clusters. It is limited to the Standard permission dependency where bit 6 grants form-fill review even with bit 9 clear.
