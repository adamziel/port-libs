# markerPDF encrypted permissions AuthEvent fail-closed current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T022415Z`

Base accepted HEAD: `81b4f7c2aca13da3f294c5aed14ebf25339eaff3`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium-backed extraction before OCR/layout/model stages. The native PHP lane keeps encrypted document content blocked unless a separate native decryption/password component is activated.
- PDF crypt-filter `/AuthEvent` metadata identifies the authorization boundary. Missing events default to `DocOpen`; `EFOpen` is for embedded-file stream opening. If an encrypted document stream or string is selected with an embedded-file-only or unknown authorization event, the native import preflight must fail closed before WordPress text import rather than reporting ordinary copy/extract-after-decryption.

## Implemented behavior

- `PdfSecurityPreflight` now scans all document-content crypt-filter roles for fail-closed conditions before returning the generic encrypted-document review policy.
- Document streams or strings whose selected crypt filter has `/AuthEvent /EFOpen` now surface `authorization_event_role_mismatch_fail_closed` and `blocked_by_document_crypt_filter_auth_event_mismatch`.
- Unknown authorization events such as `/AuthEvent /Launch` now surface `unknown_authorization_event_fail_closed` and `blocked_by_unknown_document_crypt_filter_auth_event`.
- Embedded-file streams selected with `EFOpen` remain valid review metadata. No decryption, password validation, permission enforcement, PDF action execution, Python/model execution, or external PDF tools are run.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P` preflight, unsigned `/P` normalization, indirect encryption operands, malformed reserved-bit review, duplicate `/P` review, Standard authentication-material readiness, public-key recipient envelopes, public-key DSS permission review, xref `/Prev` Encrypt inheritance, default `/EFF` inheritance, `/CFM /None` identity classification, unsupported crypt-filter method fail-closed aggregation, encrypted associated-file redaction, signature ByteRange/DSS/DocMDP/FieldMDP review, or the earlier AuthEvent defaulting/metadata-only slice.

The bounded behavior here is specifically converting document-content AuthEvent mismatches and unknown AuthEvents into crypt-filter fail-closed import boundaries.

## Focused evidence

Red-first after updating the expected current-base assertions:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php
```

Failed with `1 test files, 6 assertions, 2 failures`; both failures showed `copy_or_extract_allowed_after_decryption` where the new expectations required `copy_or_extract_allowed_but_crypt_filter_fail_closed`.

Focused test after patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php
```

Passed with `1 test files, 81 assertions, 0 failures`.

Adjacent encrypted/security regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Passed with `11 test files, 1177 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-authevent-preflight-currentbase.php
```

Emitted `permission_policy=copy_extract_allowed_but_crypt_filter_preflight_blocked`, `content_extraction_boundary=blocked_by_document_crypt_filter_auth_event_mismatch`, `text_content_policy=authorization_event_role_mismatch_fail_closed`, `fail_closed_role_names=["document_strings"]`, `fail_closed_filter_names=["EmbeddedOnly"]`, `raw_key_material_exposed=false`, `encrypted_text_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Focused PHP behavior tests move `1295 -> 1296` pass / `0` fail for the added unknown-AuthEvent TestRunner case.
- Focused AuthEvent assertions move `40 -> 81`.
- Mapped markerPDF/PDF behavior inventory adds `pdfEncryptedPermissionAuthEventPreflightCurrentBaseBehaviors=2`; no upstream denominator-total change is claimed.
- The existing WordPress AuthEvent smoke was updated with the stricter fail-closed import decision.

## Dependency closure

No new support component is needed. This slice reuses native PDF object parsing, encryption dictionary parsing, crypt-filter role review, Standard permission preflight, encrypted-text fail-closed gating, and WordPress smoke rendering.

Full Standard security-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium raster execution, and external PDF tools remain out of scope for this no-GPU/no-model markerPDF slice.
