# markerpdf encrypted duplicate crypt-filter dictionary current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T172105Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/convert.py::convert_single_pdf()` consumes parser/PDFium/pdftext extracted content before OCR/layout/model work, so encrypted PDF and crypt-filter ambiguity is a native parser preflight boundary in this no-GPU PHP lane.
- PDF encryption dictionaries can use `/CF` to define named crypt filters selected by `/StmF`, `/StrF`, and `/EFF`. Duplicate top-level `/CF` entries are ambiguous dictionary data and must not allow a later identity-looking dictionary to authorize native WordPress text import.

## Implementation

`PdfMetadataExtractor` now records `crypt_filter_dictionary_declaration_review` for top-level `/CF` declarations, including duplicate count, resolved dictionary count, declared filter names, selected-entry filter names, entry statuses, and a fail-closed flag. Associated-file redaction also treats malformed or duplicate `/CF` declarations as an encrypted boundary while preserving the existing suppression policy labels.

`PdfSecurityPreflight` now carries that declaration review into `encryption`, `crypt_filter_content_review`, and `permission_preflight`. When the `/CF` dictionary declaration is duplicate or malformed, all content roles inherit a crypt-filter dictionary fail-closed state, producing `duplicate_crypt_filter_dictionary_entries_fail_closed` and `blocked_by_duplicate_document_crypt_filter_dictionary` before decryption, permission enforcement, Python/model code, or external PDF tools.

The stale older `PdfSecurityPreflightTest.php` reserved-bit assertion was aligned with the already accepted current-base reserved-bit behavior: malformed reserved bits preserve raw decoded review metadata but expose `copy_or_extract_allowed` as `null` in import-facing preflight output.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "crypt_filter_dictionary_declaration_review"
FAIL fails closed on duplicate crypt-filter dictionaries before permission import review
1 test files, 3 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate crypt-filter dictionaries before permission import review
1 test files, 54 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php
Focused test run: 32 selected test files (root lock skipped)
32 test files, 3021 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-dictionary-currentbase.php
Passed with plain_text_blocked=true, permission_policy=copy_extract_allowed_but_crypt_filter_preflight_blocked, content_extraction_boundary=blocked_by_duplicate_document_crypt_filter_dictionary, text_content_policy=duplicate_crypt_filter_dictionary_entries_fail_closed, dictionary_declared_entry_count=2, dictionary_duplicate_entries=true, executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This does not repeat accepted encrypted fail-closed text extraction, Standard `/P` decoding, unsigned/out-of-range `/P`, reserved-bit fail-closed behavior, duplicate `/P`, missing `/P`, Standard auth material, duplicate auth material, `/Perms`, escaped auth keys, EncryptMetadata duplication, default `/EFF`, default or explicit `/CFM /None`, unsupported/missing crypt-filter methods, AuthEvent mismatch/defaulting, invalid crypt-filter key length, Standard method-generation compatibility, duplicate `/StmF`/`StrF`/`EFF` role declarations, public-key recipient envelopes, encrypted associated-file redaction, xref `/Encrypt` precedence, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is only duplicate top-level `/CF` dictionary declarations before encrypted permission import preflight.

## Dependency closure

No new support component is needed. This reuses the native PHP object scanner, dictionary parser, encryption metadata extractor, security preflight, encrypted text guard, and WordPress smoke renderer. Full Standard-handler decryption, password validation, CMS/PKCS#7 public-key permission decoding, permission enforcement, signing/signature validation, revocation checks, live OCR, Surya/Texify/Torch execution, PDFium rendering, and external PDF tools remain intentionally out of scope.
