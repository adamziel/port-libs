# markerPDF encrypted duplicate permission preflight current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T011736Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260605T011736Z`
Base accepted HEAD: `c6112ce2e1611534e43d39ec57fc44e1f843be3a`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`; searchable-PDF text extraction is delegated through `marker/pdf/extract_text.py` to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF dictionary keys are required to be unique. PDF Association forensic guidance calls out duplicate dictionary keys as invalid but common in real-world files, and warns that APIs may hide which value is selected: https://pdfa.org/challenges-in-the-forensic-analysis-of-pdf-files/
- For encrypted PDF import, `/P` Standard security-handler permission words are review metadata until a native decryption/password-validation component exists. Duplicate top-level `/P` declarations therefore cannot be treated as a reliable grant for WordPress text import.

## Implementation

- `PdfMetadataExtractor` now inventories every top-level `/P` entry in the selected encryption dictionary as `standard_permission_word_review`.
- Duplicate `/P` declarations are marked `duplicate_standard_permission_entries_review`, with normalized signed/unsigned/hex values and conflicting permission-bit statuses.
- `PdfSecurityPreflight` now treats duplicate `/P` declarations as malformed permission preflight: encrypted text remains blocked, permission bits are not reliable, `copy_or_extract_allowed` is `null`, and `permission_word_duplicate_entries` appears in review reasons.
- Existing single malformed reserved-bit permissions remain backward-compatible: their decoded bit value is still review-visible but unreliable.

## Red-first evidence

After adding only the duplicate `/P` fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL fails closed when Standard encryption dictionary declares duplicate permission words
Expected: 'permissions_malformed_blocked_without_decryption'
Actual: 'copy_extract_allowed_after_decryption'
1 test files, 93 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
1 test files, 117 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php
7 test files, 903 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-permission-preflight-currentbase.php
emits text_blocked=true, policy=permissions_malformed_blocked_without_decryption, permission_word_status=duplicate_standard_permission_entries_review, duplicate_permission_entries=true, conflicting_permission_names=["copy_or_extract"], copy_or_extract_allowed=null, raw_auth_material_exposed=false, executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned `/P` normalization, indirect encryption operand resolution, malformed reserved-bit review, unsupported handler review, Standard authentication-material readiness, public-key recipient envelopes, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, crypt-filter content role preflight, or signature ByteRange/DSS/DocMDP/FieldMDP review.

The bounded behavior is specifically duplicate top-level `/P` declarations in a Standard encryption dictionary and the permission-reliability boundary before WordPress import.

## Dependency closure

No new support component is needed. This slice reuses the native PHP PDF object/trailer parser, encryption dictionary parser, Standard permission-bit review, encrypted-text fail-closed gate, security preflight report path, and WordPress smoke renderer.

Full password validation, Standard security-handler decryption, authenticated permission validation from `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, revocation checks, trust-chain validation, live OCR, Surya/Texify/Torch model execution, PDFium raster execution, and external PDF tools remain out of scope for this no-GPU/no-model markerPDF slice.
