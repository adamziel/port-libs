# markerpdf encrypted duplicate crypt-filter role preflight current base

## Scope

- Lane: markerpdf
- Micro-slice: markerpdf-encrypted-permissions-preflight-current-base-20260605T093129Z
- Accepted base: cd9b1dd080be5ba6f083eb763beca98a277cc0e1
- Upstream source-truth boundary: PDF encryption dictionaries using crypt filters select document streams, strings, and embedded-file streams through `/StmF`, `/StrF`, and `/EFF`. Duplicate role keys are ambiguous because a native dictionary map can otherwise silently let the last value win.

## Behavior

This patch adds a review-only crypt-filter role declaration audit before encrypted import decisions:

- duplicate `/StmF`, `/StrF`, or `/EFF` top-level declarations are preserved as declaration review metadata;
- malformed non-name crypt-filter role operands are classified for the same fail-closed path;
- existing selected-filter metadata remains visible, so the report still shows which last parsed role value would have won;
- duplicate or malformed document text roles now drive `copy_extract_allowed_but_crypt_filter_preflight_blocked`;
- no decryption, permission enforcement, model execution, external PDF tooling, raw owner/user key exposure, or encrypted stream-byte exposure is introduced.

## Red-First Evidence

Current-base probe before the implementation used a Standard V4 fixture with `/StmF /StdCF /StmF /ClearStreams`. It returned:

```text
text=""
permission_policy=copy_extract_allowed_after_decryption
text_policy=identity_filters_review_only_encrypted_document_boundary
boundary=blocked_until_decryption_password_available
declaration_review_present=no
fail_closed_roles=[]
```

The patched source returns `copy_extract_allowed_but_crypt_filter_preflight_blocked`,
`duplicate_crypt_filter_role_entries_fail_closed`, and
`blocked_by_duplicate_document_crypt_filter_roles` for the same fixture.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate Standard crypt-filter content role declarations before import
1 test files, 103 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsupportedCryptFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
29 PASS lines
8 test files, 1183 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-role-currentbase.php
emits plain_text_blocked=true, permission_policy=copy_extract_allowed_but_crypt_filter_preflight_blocked, content_extraction_boundary=blocked_by_duplicate_document_crypt_filter_roles, duplicate_pdf_names=["StmF"], executes_decryption=false, executes_python_or_models=false, executes_external_pdf_tools=false
```

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/src/PdfSecurityPreflight.php
No syntax errors detected in lanes/markerpdf/src/PdfSecurityPreflight.php

php -l lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateCryptFilterRoleCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-role-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-crypt-filter-role-currentbase.php

php -r '$json=file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
clean
```

## Non-Overlap

This is not the accepted encrypted parameter, permission-word token, default crypt-filter, unsupported crypt-filter method, AuthEvent, key-length, `/Perms`, public-key recipient, or stream-filter `/Crypt` DecodeParms work. It owns only duplicate/malformed top-level crypt-filter role declarations feeding the encrypted-permission preflight.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP dictionary parsing, encryption metadata extraction, text extraction blocking, and security preflight plumbing. GPU/OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF direction.
