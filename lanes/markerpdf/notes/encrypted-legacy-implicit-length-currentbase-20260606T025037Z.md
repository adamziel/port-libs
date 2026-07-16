# Encrypted Legacy Implicit Length Preflight Current Base

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, encrypted PDF handling remains a native PHP security preflight before text import, OCR, layout, table, equation, or model stages.

For the PDF Standard security handler, legacy `/V 1 /R 2` dictionaries use the 40-bit RC4 algorithm. An omitted top-level `/Length` is therefore not the same as an explicitly declared `/Length 40`: the preflight may use the implicit 40-bit value for compatibility review, but import metadata should still show that the key length was defaulted rather than present in the encryption dictionary.

## Implementation

- `PdfMetadataExtractor` now records `key_length_explicit=false`, `key_length_defaulted=true`, and `key_length_source=standard_security_handler_v1_implicit_40_bit` when a Standard `/V 1` encryption dictionary omits `/Length`.
- Standard security-handler parameter review now reports `key_length_present=false` and `key_length_status=standard_security_handler_key_length_implicit_40_bit` for that implicit legacy path while keeping `key_length_bits=40`, `key_length_valid=true`, and `parameters_well_formed=true`.
- `PdfSecurityPreflight` surfaces the same key-length provenance on `permission_preflight`, `permission_handler_review`, and the sanitized `encryption` review summary so WordPress import callers can distinguish explicit and implicit key lengths without inspecting raw PDF objects.
- Encrypted text remains blocked; permission bits are still review-only and only allow copy/extract after decryption.

## Focused evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyImplicitLengthCurrentBaseTest.php
```

Result before source edit: failed after 10 assertions because `key_length_explicit` was absent and the legacy implicit length looked like ordinary explicit key-length metadata.

After source edit:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfSecurityPreflight.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionLegacyImplicitLengthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-legacy-implicit-length-currentbase.php
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyImplicitLengthCurrentBaseTest.php
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionLegacyImplicitLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAes256LengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionVersionRevisionCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
php lanes/markerpdf/examples/wordpress-pdf-encrypted-legacy-implicit-length-currentbase.php
```

Results: syntax checks passed; the new focused test passed at `1 test files, 43 assertions, 0 failures`; the adjacent encrypted permission/version/key-length family passed at `5 test files, 681 assertions, 0 failures`; the WordPress smoke emitted `encrypted_text_blocked=true`, `key_length_bits=40`, `key_length_explicit=false`, `key_length_defaulted=true`, `parameter_key_length_present=false`, `parameter_key_length_status=standard_security_handler_key_length_implicit_40_bit`, `permission_policy=copy_extract_allowed_after_decryption`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted Standard permission bit decoding, revision-bit applicability, invalid AES-256 missing `/Length` fail-closed handling, explicit `/Length 40` revision-2 fixtures, duplicate Standard parameters, unsupported version/revision mismatch, malformed `/P` operands, duplicate `/P`, authentication material review, `/Perms`, crypt-filter role/method/AuthEvent/key-length review, trailer `/Encrypt` precedence, encrypted associated-file redaction, signature review, stream-filter `/Crypt`, OCR/model execution, or external PDF tooling. The bounded behavior is only legacy Standard `/V 1` omitted `/Length` provenance before encrypted import preflight.

## Dependency closure

No new support component is needed. This reuses the native PHP object scanner, top-level dictionary parser, encryption metadata extractor, Standard permission review, security preflight, encrypted text guard, and WordPress smoke renderer. Full Standard-handler decryption, password validation, permission enforcement, public-key CMS/PKCS#7 permission decoding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
