# markerPDF encrypted EncryptMetadata trailing operand current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T180319Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260608T180319Z`
Base accepted HEAD: `dd923d65163e791b0e0ab69fe21f3c66d9e1c5ea`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text and metadata through parser preflight before OCR/model stages. This PHP lane keeps encrypted content and encrypted metadata fail-closed unless native parsing proves a safe unencrypted boundary.
- PDF encryption dictionaries define `/EncryptMetadata` as an optional boolean that defaults to `true`. A clean single `false` may preserve root XMP without decryption, but `false` followed by another top-level operand is not a single boolean declaration and must not be trusted.

## Change

- `PdfMetadataExtractor::encryptMetadataDeclarationReview()` now reads `/EncryptMetadata` through top-level value reviews rather than trusting only the first token.
- Direct values such as `/EncryptMetadata false 6 0 R` and resolved indirect object bodies such as `false /ShadowMetadata` are reported as `encrypt_metadata_trailing_operand_review`.
- Malformed tailed declarations set `encrypt_metadata_trusted=false`, `encrypt_metadata_defaulted_fail_closed=true`, and effective `encrypt_metadata=true`, suppressing encrypted root XMP before WordPress metadata promotion.
- Clean single boolean declarations are preserved: omitted `/EncryptMetadata` still defaults true, and a single valid `false` remains trusted by the existing encrypted metadata policy.

## Evidence

Red-first focused run before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataTrailingOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when EncryptMetadata false has a trailing direct operand
FAIL fails closed when indirect EncryptMetadata false resolves to a trailing operand
1 test files, 4 assertions, 2 failures
```

Both failures showed metadata `source` as `["encryption","xmp"]` where the encrypted XMP stream should have been suppressed.

Focused verification after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataTrailingOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when EncryptMetadata false has a trailing direct operand
PASS fails closed when indirect EncryptMetadata false resolves to a trailing operand
1 test files, 102 assertions, 0 failures
```

Adjacent encryption metadata/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1714 assertions, 0 failures
```

Broader encrypted current-base/security regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
Focused test run: 78 selected test files (root lock skipped)
78 test files, 7444 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-encryptmetadata-trailing-operand-currentbase.php
```

The smoke exits `0` and reports `encrypted_text_blocked=true`, `xmp_stream_policy=suppressed_encrypted_metadata_stream`, `encrypt_metadata_status=malformed_encrypt_metadata_declaration_review`, `entry_statuses=["encrypt_metadata_trailing_operand_review"]`, `trailing_operand_shape=indirect_reference`, `trailing_operand_preview=6 0 R`, `raw_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and status hygiene:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionEncryptMetadataTrailingOperandCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-encryptmetadata-trailing-operand-currentbase.php
php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid\n";'
```

All changed PHP files passed syntax checks, and `lane-status.json` decoded successfully.

## Non-Overlap

This does not repeat accepted encrypted fail-closed text extraction, clean `/EncryptMetadata false` XMP preservation, duplicate `/EncryptMetadata` declarations, duplicate/trailing trailer `/Encrypt`, Standard `/P` direct or indirect trailing operands, authentication material trailing operands, duplicate auth material, commented indirect scalar operands, crypt-filter method/AuthEvent/key-length/default-role review, public-key recipient envelopes, signature/DSS/DocMDP/FieldMDP review, xref `/Prev` Encrypt selection, OCR/model execution, or external PDF tool execution. The bounded behavior here is specifically malformed direct and resolved indirect trailing operands on `/EncryptMetadata`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary value-review parser, metadata source policy, security preflight report, text extractor, and lane-local WordPress smoke harness.

Still intentionally out of scope under the current markerPDF override: full decryption/password validation, permission enforcement, public-key CMS/PKCS#7 validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, external PDF tools, and exact upstream model benchmark parity.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs. A useful encrypted-PDF follow-up would be a distinct security dictionary boundary such as crypt-filter role selection, public-key recipient review, or permission-word operand trust that is not another `/EncryptMetadata` trailing-operand slice.
