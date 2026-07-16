# markerpdf encrypted duplicate decoded crypt-filter name current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T011753Z`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/convert.py::convert_single_pdf()` consumes parser/PDFium/pdftext extracted content before OCR/layout/model work, so encrypted PDF security review remains a native parser preflight boundary in this no-GPU PHP lane.
- PDF name tokens decode `#NN` hexadecimal escapes before semantic comparison. A single encryption `/CF` dictionary containing `/StdCF` and `/Std#43F` has two entries for the same decoded crypt-filter name, so it is ambiguous even when the later alias appears to select `/CFM /Identity`.

## Implementation

`PdfMetadataExtractor` now detects duplicate decoded names inside the selected crypt-filter dictionary, records `duplicate_filter_name_entries`, `duplicate_filter_names`, `duplicate_filter_name_count`, and selected-entry duplicate names in `crypt_filter_dictionary_declaration_review`, and marks that declaration fail-closed with `duplicate_crypt_filter_name_entries_review`.

`PdfSecurityPreflight` now carries those duplicate-filter-name fields into each content role and the aggregate crypt-filter review. The permission preflight maps the condition to `duplicate_crypt_filter_name_entries_fail_closed` and `blocked_by_duplicate_document_crypt_filter_name`, so copy/extract permission bits cannot authorize native text import when a later escaped alias hides an encrypted filter definition.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterNameCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate decoded crypt-filter names before permission import review
Values are not identical
Expected: array (
  0 => 'encrypted_document',
  1 => 'encrypted_text_extraction_blocked',
  2 => 'copy_or_extract_allowed_but_crypt_filter_fail_closed',
  3 => 'crypt_filter_text_fail_closed',
)
Actual: array (
  0 => 'encrypted_document',
  1 => 'encrypted_text_extraction_blocked',
  2 => 'copy_or_extract_allowed_but_decryption_required',
)
1 test files, 3 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterNameCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate decoded crypt-filter names before permission import review
1 test files, 60 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterNameCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedCryptFilterParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionParameterSelectedEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionAuthEventCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionEscapedAuthKeysCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 827 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-name-currentbase.php
Passed with plain_text_blocked=true, content_extraction_boundary=blocked_by_duplicate_document_crypt_filter_name, text_content_policy=duplicate_crypt_filter_name_entries_fail_closed, dictionary_duplicate_filter_names=["StdCF"], executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, Standard `/P` decoding, unsigned/out-of-range `/P`, reserved-bit fail-closed behavior, duplicate `/P`, missing `/P`, Standard auth material, duplicate auth material, `/Perms`, escaped Standard auth keys, EncryptMetadata duplication, default `/EFF`, default or explicit `/CFM /None`, unsupported/missing crypt-filter methods, AuthEvent mismatch/defaulting, invalid crypt-filter key length, Standard method-generation compatibility, duplicate top-level `/CF` dictionary declarations, duplicate `/StmF`/`/StrF`/`/EFF` role declarations, malformed crypt-filter parameters, public-key recipient envelopes, encrypted associated-file redaction, xref `/Encrypt` precedence, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is only duplicate decoded crypt-filter names inside one `/CF` dictionary before encrypted permission import preflight.

## Dependency closure

No new support component is needed. This reuses the native PHP object scanner, dictionary parser, PDF name decoder, encryption metadata extractor, security preflight, encrypted text guard, and WordPress smoke renderer. Full Standard-handler decryption, password validation, CMS/PKCS#7 public-key permission decoding, permission enforcement, signing/signature validation, revocation checks, live OCR, Surya/Texify/Torch execution, PDFium rendering, and external PDF tools remain intentionally out of scope.
