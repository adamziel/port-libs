# markerpdf encrypted malformed crypt-filter parameter preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260606T091820Z`

Base accepted HEAD: `75e47bda11781d9e0c3af4331acfa9e1a02264e1`

## Source truth

- Upstream `sddai/markerPDF` keeps searchable-PDF text extraction on the parser/PDFium/pdftext path before OCR/layout/model stages. This native no-GPU lane keeps encrypted PDF content blocked until a real decryption component exists.
- PDF crypt-filter dictionaries use `/CFM`, `/AuthEvent`, and `/Length` to select stream/string encryption behavior and authorization boundaries. A malformed operand for any of those parameters is security-sensitive even when the current extractor can coerce the value, so the preflight must fail closed before importing encrypted text.

## Implementation

- `PdfMetadataExtractor` now reviews selected crypt-filter parameter declarations for malformed single operands as well as duplicates:
  - `/CFM` and `/AuthEvent` must be PDF name operands.
  - `/Length` must be an integer token operand.
  - unresolved indirect references and composite array/dictionary operands are preserved as malformed parameter rows.
- `PdfSecurityPreflight` now carries `malformed_parameter_names` from selected crypt-filter rows into role and content-review aggregates:
  - document text policy: `malformed_crypt_filter_parameter_entry_fail_closed`
  - document boundary: `blocked_by_malformed_document_crypt_filter_parameter`
  - duplicate and malformed crypt-filter parameter summaries are now separate.
- The slice is preflight-only. It does not decrypt content, validate passwords, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user authentication bytes.

## Red-first evidence

Before the implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedCryptFilterParameterCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when selected crypt-filter method is a literal string operand
FAIL fails closed when selected crypt-filter AuthEvent is a literal string operand
FAIL fails closed when selected crypt-filter Length is an array operand
1 test files, 0 assertions, 3 failures
```

The failures occurred because `parameter_declaration_review` was absent for malformed single crypt-filter parameter operands.

## Verification

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfSecurityPreflight.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionMalformedCryptFilterParameterCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-malformed-crypt-filter-parameter-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
```

Result: no syntax errors, `lane-status json ok`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedCryptFilterParameterCurrentBaseTest.php
```

Result: `1 test files, 243 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedCryptFilterParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `15 test files, 2564 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php$|PdfEncryptedPermissionsPreflightCurrentBaseTest\.php$|PdfSecurity.*CurrentBaseTest\.php$|PdfSecurityPreflightTest\.php$' | sort)
```

Result: `59 test files, 5366 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-malformed-crypt-filter-parameter-currentbase.php
```

Result: emits `plain_text_blocked=true`, `content_extraction_boundary=blocked_by_malformed_document_crypt_filter_parameter`, `text_content_policy=malformed_crypt_filter_parameter_entry_fail_closed`, `malformed_parameter_names=["CFM"]`, `fail_closed_role_names=["document_streams","document_strings"]`, `fail_closed_filter_names=["DocCF"]`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, trailer `/Encrypt` selection/duplicates, Standard permission word decoding, unsigned/out-of-range `/P`, duplicate `/P`, missing `/P`, reserved-bit review, Standard top-level `/Filter`/`/V`/`/R`/`/Length` parameter validation, authentication material, duplicate auth material, `/Perms`, escaped auth keys, EncryptMetadata declaration handling, `/CF` dictionary duplicate review, `/StmF`/`/StrF`/`/EFF` role duplicate/malformed review, default `/EFF`, omitted `/CFM` defaulting, unsupported methods, AuthEvent mismatch/defaulting, invalid crypt-filter key lengths, method-generation compatibility, public-key recipient envelopes, duplicate crypt-filter parameters, encrypted associated-file redaction, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is malformed single parameter operands inside a selected crypt-filter dictionary.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary parser, encryption metadata extractor, crypt-filter preflight review, and WordPress smoke path. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
