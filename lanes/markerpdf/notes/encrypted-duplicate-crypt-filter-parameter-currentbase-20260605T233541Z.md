# markerpdf encrypted duplicate crypt-filter parameter preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T233541Z`

Base accepted HEAD: `ad3675be5d124eab763e55df537a512415a0dbe8`

## Source truth

- Upstream `sddai/markerPDF` performs searchable-PDF extraction through the parser/PDFium/pdftext boundary before OCR/layout/model stages. This native no-GPU lane keeps encrypted PDF content blocked until a real decryption component exists.
- PDF crypt-filter dictionaries are ordinary PDF dictionaries whose `/CFM`, `/AuthEvent`, and `/Length` entries control whether streams/strings are encrypted and which authorization boundary applies. Duplicate entries are ambiguous and must not let a final `/CFM /Identity` hide an earlier encrypted method in the same selected filter.

## Implementation

- `PdfMetadataExtractor` now records `parameter_declaration_review` on each crypt-filter row when `/CFM`, `/AuthEvent`, or `/Length` is duplicated.
- `PdfSecurityPreflight` carries that per-filter duplicate-parameter review into role rows and treats selected duplicate crypt-filter parameters as fail-closed content policy:
  - document text policy: `duplicate_crypt_filter_parameter_entries_fail_closed`
  - document boundary: `blocked_by_duplicate_document_crypt_filter_parameters`
  - role/filter aggregates: selected `/StmF` and `/StrF` roles fail closed on `DocCF` while clear embedded-file identity payload review remains unchanged.
- The slice is preflight-only. It does not decrypt content, validate passwords, enforce permissions, execute PDF actions, run Python/model code, or expose raw owner/user authentication bytes.

## Red-first evidence

Before the implementation:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterParameterCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed when selected crypt-filter dictionaries duplicate the CFM parameter
Expected review reasons included copy_or_extract_allowed_but_crypt_filter_fail_closed / crypt_filter_text_fail_closed.
Actual review reasons ended at copy_or_extract_allowed_but_decryption_required.
1 test files, 3 assertions, 1 failures
```

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterParameterCurrentBaseTest.php
```

Result: `1 test files, 79 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `13 test files, 2105 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php$|PdfEncryptedPermissionsPreflightCurrentBaseTest\.php$|PdfSecurity.*CurrentBaseTest\.php$|PdfSecurityPreflightTest\.php$' | sort)
```

Result: `50 test files, 4336 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-parameter-currentbase.php
```

Result: emits `plain_text_blocked=true`, `content_extraction_boundary=blocked_by_duplicate_document_crypt_filter_parameters`, `text_content_policy=duplicate_crypt_filter_parameter_entries_fail_closed`, `duplicate_parameter_names=["CFM"]`, `fail_closed_role_names=["document_streams","document_strings"]`, `fail_closed_filter_names=["DocCF"]`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, trailer `/Encrypt` selection/duplicates, Standard permission word decoding, unsigned/out-of-range `/P`, duplicate `/P`, missing `/P`, reserved-bit review, Standard `/V`/`/R`/top-level `/Length` parameter validation, authentication material, duplicate auth material, `/Perms`, escaped auth keys, EncryptMetadata declaration handling, `/CF` dictionary duplicate review, `/StmF`/`/StrF`/`/EFF` role duplicate review, default `/EFF`, omitted `/CFM` defaulting, unsupported methods, AuthEvent mismatch/defaulting, invalid crypt-filter key lengths, method-generation compatibility, public-key recipient envelopes, encrypted associated-file redaction, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is only duplicate parameter declarations inside a selected crypt-filter dictionary.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, top-level dictionary parser, encryption metadata extractor, crypt-filter preflight review, and WordPress smoke path. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch models, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
