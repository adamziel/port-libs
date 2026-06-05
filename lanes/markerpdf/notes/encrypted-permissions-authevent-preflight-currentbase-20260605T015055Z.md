# markerpdf encrypted permissions AuthEvent preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T015055Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to pdftext/PDFium-style extraction before model and Markdown stages. This native PHP lane keeps encrypted content fail-closed unless a future decryption/password component is explicitly available.
- PDF crypt-filter dictionaries use `/AuthEvent` to say when authorization is needed. Missing `/AuthEvent` defaults to document-open authorization (`DocOpen`), while `EFOpen` is an embedded-file-open boundary and should be surfaced when selected for document streams or strings.

## Implementation

- `PdfMetadataExtractor` now records defaulted crypt-filter authorization events as `auth_event=DocOpen`, `auth_event_defaulted=true`, and `auth_event_source=pdf_default_doc_open` when an encrypted named crypt filter omits `/AuthEvent`.
- `PdfSecurityPreflight` now reports crypt-filter authorization-event status per selected role, including defaulted role/filter names and EFOpen role-mismatch review rows.
- Legacy Standard security handlers without crypt-filter role entries are no longer treated as undeclared crypt-filter failures. They keep the existing encrypted-document/decryption-required boundary.
- The slice remains preflight-only: no decryption, password validation, permission enforcement, PDF action execution, Python/model execution, external PDF tools, or raw owner/user key exposure.

## Focused evidence

New focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php
```

Passed with `1 test files, 40 assertions, 0 failures`.

Regression caught during the adjacent encrypted set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Initially failed `PdfEncryptedPermissionRevisionBitCurrentBaseTest.php` because legacy `/V 1` Standard encryption has no crypt-filter roles. After the guard fix, the same command passed with `11 test files, 1136 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-authevent-preflight-currentbase.php
```

Emitted `encrypted_text_blocked=true`, `doc_stream_auth_event=DocOpen`, `doc_stream_auth_event_defaulted=true`, `auth_event_defaulted_role_names=["document_streams"]`, `auth_event_mismatch_role_names=["document_strings"]`, `raw_key_material_exposed=false`, `encrypted_text_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P` preflight, unsigned `/P` normalization, indirect encryption operands, malformed reserved-bit review, duplicate `/P` review, Standard authentication-material readiness, public-key recipient envelopes, public-key DSS permission review, xref `/Prev` Encrypt inheritance, default `/EFF` inheritance, `/CFM /None` identity classification, unsupported crypt-filter method fail-closed aggregation, encrypted associated-file redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review.

The bounded behavior is specifically crypt-filter `/AuthEvent` defaulting and selected-role review before encrypted WordPress import decisions.

## Status delta

- Focused PHP behavior tests move `1263 -> 1264` pass / `0` fail for the added TestRunner case.
- WordPress scenarios move `1229 -> 1230` for the added smoke.
- No upstream denominator-total change is claimed.

## Dependency closure

No new support component is needed. This reuses native PDF object parsing, encryption dictionary parsing, crypt-filter role review, Standard permission preflight, encrypted-text fail-closed gating, and WordPress smoke rendering.

Full Standard security-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium raster execution, and external PDF tools remain out of scope for this no-GPU/no-model markerPDF slice.
