# Encrypted Permission Text Extraction Boundary Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T232301Z`  
Base: `9841e2a91c07f28b843347e26ec0f272d338572d`

## Source Truth

- Native no-GPU markerPDF scope: preserve searchable-PDF security preflight without decrypting content, enforcing permissions, invoking OCR/models, raster rendering, or external PDF tools.
- PDF Standard security handler permission bit 5 controls ordinary copy/extract, while permission bit 10 controls extraction for accessibility in revision 3+ permission words. WordPress text import must not treat accessibility-only permission as an ordinary copy/extract grant.

## Implementation

- `PdfSecurityPreflight` now emits `standard_permission_text_extraction_review` beside the existing operation and print-quality permission reviews.
- The review exposes `copy_or_extract_allowed`, `accessibility_extract_allowed`, the operation row statuses, and a WordPress-specific boundary:
  `blocked_by_copy_permission_denial_accessibility_review_only` when accessibility extraction is syntactically allowed but copy/extract is denied.
- Existing import policy remains fail-closed as `copy_extract_denied_by_permissions`; the change is additive review metadata for callers that need to distinguish accessibility review from WordPress import permission.

## Evidence

Red-first current-base check before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTextExtractionBoundaryCurrentBaseTest.php
=> missing standard_permission_text_extraction_review; 1 test file / 8 assertions / 1 failure
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTextExtractionBoundaryCurrentBaseTest.php
=> 1 test file / 57 assertions / 0 failures
```

Adjacent encrypted-permission family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionTextExtractionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintDependencyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionFormFillDependencyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintQualityReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationAuthReadinessCurrentBaseTest.php
=> 7 test files / 661 assertions / 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-text-extraction-boundary-currentbase.php
=> exits 0; emits policy=copy_extract_denied_by_permissions, copy_or_extract_allowed=false,
   accessibility_extract_allowed=true, text_extraction_review_status=accessibility_extract_allowed_but_copy_extract_denied,
   raw_auth_material_exposed=false, executes_decryption=false,
   executes_permission_enforcement=false, executes_python_or_models=false,
   executes_external_pdf_tools=false
```

## Non-Overlap

Avoided prior accepted encrypted permission clusters for duplicate permission words, malformed permission operands, auth material readiness, print dependency, print-quality review, form-fill dependency, crypt-filter role boundaries, and CMap/font/source-width behavior. This slice is limited to the copy/extract versus accessibility extraction preflight boundary.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP permission-bit parser and operation-review rows in `PdfSecurityPreflight`.

## Next Task

Continue no-GPU markerPDF work on non-overlapping native parser/security surfaces such as encrypted metadata edge cases, xref repair, forms, annotations, font encodings, stream filters, or supplied-boundary table/equation handoffs.
