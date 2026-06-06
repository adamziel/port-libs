# MarkerPDF encrypted permission operation preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T203442Z`

Base accepted HEAD: `1a04e44c91a22f3d4217b77b07bd40823238f1c6`

## Behavior

`PdfSecurityPreflight` now exposes `standard_permission_operation_review` at the top level, under `permission_preflight`, and under the summarized `encryption` review.

The review maps decoded Standard security-handler permission bits into operation rows for:

- `print`
- `modify_contents`
- `copy_or_extract`
- `add_or_modify_annotations`
- `fill_form_fields`
- `extract_for_accessibility`
- `assemble_document`
- `high_quality_print`

Each row preserves the raw bit review status while adding import-facing status:

- `allowed_by_permission_bit_pending_authentication`
- `denied_by_permission_bit`
- `not_applicable_for_revision`

Syntactically allowed operations stay `native_import_allowed_now=false` because this no-GPU/native-parser lane does not validate passwords, authenticate `/Perms`, decrypt content, or enforce permissions. This lets WordPress import review distinguish operations that are denied by `/P` from operations that may be allowed after a future authenticated decryption path.

## Source Truth And Non-Overlap

Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the current no-GPU lane, encrypted PDFs are handled as native parser/security preflight before any OCR/model conversion. PDF Standard security-handler permission words define operation bits, but they do not authorize visible import in this PHP lane without password validation, permission authentication, and decryption.

This does not repeat existing slices for Standard `/P` decoding, revision bit applicability, missing/malformed/duplicate permission words, authentication trust, `/Perms` digest review, crypt-filter role/AuthEvent/key-length fail-closed behavior, public-key recipient envelopes, encrypted attachment redaction, or signature/DSS/DocMDP review.

## Focused Evidence

Red-first run after adding the test and before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationPreflightCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
... Undefined array key "standard_permission_operation_review" ...
1 test files, 0 assertions, 2 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationPreflightCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps revision two Standard permission bits to operation preflight rows without granting import
PASS keeps revision four allowed operations pending authentication before WordPress import
1 test files, 61 assertions, 0 failures
```

Family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*Test.php
46 test files, 4003 assertions, 0 failures
```

Security preflight check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
1 test files, 494 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-operation-preflight-currentbase.php
```

Result: exits 0 and reports `operation_count=8`, pending-authentication operation names for syntactically allowed operations, denied operation names for modify/annotation bits, `native_import_allowed_operation_names=[]`, `raw_auth_material_exposed=false`, and all decryption/permission-enforcement/Python/model/OCR/external-tool flags false.

## Status Delta

- Focused PHP behavior tests: `2676 -> 2678` PASS cases.
- Focused assertions added in the new direct test: `61`.
- WordPress scenarios: `2255 -> 2256`.
- Upstream denominator/mapped inventory: unchanged for this bounded current-base behavior slice.

## Dependency Closure

No new support component is needed. This reuses native PHP encryption dictionary parsing, Standard permission bit decoding, Standard authentication trust metadata, encrypted text fail-closed extraction, and security preflight reporting.

Still intentionally out of scope: Standard-handler decryption, password validation, `/Perms` authentication, public-key CMS permission decoding, permission enforcement, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools.

Root harness status: not run - isolated micro-slice.
