# markerpdf encrypted permissions unsupported crypt-filter preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T004517Z`

## Source truth

- Upstream `sddai/markerPDF` delegates searchable PDF text extraction to pdftext/PDFium before Markdown/model stages. The native PHP lane therefore keeps encrypted content fail-closed unless a future native decryption/password component is explicitly available.
- PDF crypt-filter dictionaries select content-role filters through `/StmF`, `/StrF`, and `/EFF`. `Identity`/`None` can mark a role clear, while encryption methods such as `V2`, `AESV2`, and `AESV3` still require authorization/decryption. Unknown, missing, undeclared, or unsupported methods must remain fail-closed for native WordPress import.

## Implementation

- `PdfSecurityPreflight` now aggregates crypt-filter role rows into explicit `fail_closed_role_names`, `fail_closed_filter_names`, and `fail_closed_role_count` metadata.
- Document text policy now promotes undeclared, missing, unknown, and unsupported document-stream/string crypt filters to fail-closed text policies instead of the generic encrypted-document boundary.
- Permission preflight now preserves Standard permission-bit review but changes the import boundary to `copy_extract_allowed_but_crypt_filter_preflight_blocked` when copy/extract is allowed yet the selected document crypt filter is unsupported or unresolved.
- The slice remains review-only: no decryption, password validation, permission enforcement, Python/model execution, external PDF tools, or raw owner/user key exposure.

## Focused evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php
```

Failed before the implementation after 7 assertions because review reasons still reported `copy_or_extract_allowed_but_decryption_required` and the report had no fail-closed crypt-filter aggregate fields.

After implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php
```

Passed with `1 test files, 62 assertions, 0 failures`.

Adjacent encrypted/security regression set:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Passed with `8 test files, 937 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-unsupported-crypt-filter-currentbase.php
```

Emitted `encrypted_text_blocked=true`, `permission_policy=copy_extract_allowed_but_crypt_filter_preflight_blocked`, `content_extraction_boundary=blocked_by_unsupported_document_crypt_filter_method`, `crypt_filter_text_fail_closed=true`, `fail_closed_role_names=["document_streams","document_strings"]`, raw key/text exposure false, and all decryption/model/external-tool flags false.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed reserved-bit review, unsupported security-handler review, Standard authentication digest hashing, public-key recipient permission review, xref `/Prev` Encrypt inheritance, default `/EFF` inheritance, explicit `CFM /None` handling, explicit `/EFF` missing-filter review, encrypted associated-file metadata redaction, signature ByteRange/DSS/DocMDP/FieldMDP review, or the recent stream-filter DecodeParms boundary.

The bounded new behavior is specifically unsupported or unknown document crypt-filter method aggregation and permission-preflight boundary selection.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, encryption metadata parser, crypt-filter role review, permission preflight, fail-closed encrypted text gate, and WordPress smoke renderer.

Full Standard security-handler decryption, password validation, `/Perms` authentication, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
