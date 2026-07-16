# markerpdf encrypted duplicate trailer Encrypt preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T175625Z`

Accepted base: `e2829f448a223bbf323c61da0a50842dcd8c1e43`

## Source truth

- Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- `marker/convert.py::convert_single_pdf()` consumes parser/PDFium/pdftext extracted content before OCR/layout/model work, so encrypted PDF admission is a native parser preflight boundary in this no-GPU PHP lane.
- A selected trailer `/Encrypt` declaration is security-sensitive. Duplicate top-level `/Encrypt` keys are ambiguous and must not let one parser clear encryption with `/Encrypt null` while another parser sees a later encrypted dictionary and permission grant.

## Implementation

- `PdfMetadataExtractor` now reads top-level trailer/xref-stream `/Encrypt` declarations as a list. A selected duplicate `/Encrypt` declaration becomes sanitized encrypted metadata with:
  - `malformed_encrypt_dictionary=true`
  - `encrypt_dictionary_resolved=false`
  - `duplicate_encrypt_dictionary_entries=true`
  - `encrypt_operand_status=duplicate_encrypt_dictionary_entries_review`
  - entry status/shape summaries without raw authentication bytes.
- `PdfSecurityPreflight` carries that duplicate declaration review into both `encryption` and `permission_preflight`, producing `permissions_unknown_blocked_without_decryption` instead of trusting duplicate-derived Standard `/P` bits.
- `PdfTextExtractor` now treats duplicate selected trailer/xref-stream `/Encrypt` entries as encrypted, so a first `/Encrypt null` cannot clear a later duplicate encrypted declaration and leak page text.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "encryption" in lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php on line 58
FAIL fails closed when selected trailer declares duplicate Encrypt entries
Values are not identical
Expected: ''
Actual: 'Duplicate trailer Encrypt encrypted text leak'
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed when selected trailer declares duplicate Encrypt entries
1 test files, 55 assertions, 0 failures
```

Adjacent encrypted/trailer/security family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 33 selected test files (root lock skipped)
33 test files, 3034 assertions, 0 failures
```

Metadata/security focused check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1411 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-trailer-encrypt-currentbase.php
```

Result: emits `plain_text_blocked=true`, `encrypted=true`, `duplicate_encrypt_dictionary_entries=true`, `encrypt_dictionary_declared_entry_count=2`, `encrypt_operand_status=duplicate_encrypt_dictionary_entries_review`, `permission_policy=permissions_unknown_blocked_without_decryption`, `duplicate_permission_grant_suppressed=true`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Baseline observation:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` still has two UseCMap failures on this worktree.
- A read-only `/tmp` checkout from unmodified accepted `HEAD` reproduced the same two failures, so they are not introduced by this duplicate `/Encrypt` slice.

Handoff checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfSecurityPreflight.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-duplicate-trailer-encrypt-currentbase.php
```

Result: no syntax errors detected.

```text
jq empty lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Result: JSON parses and diff check is clean.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, malformed unresolved trailer `/Encrypt`, `/Encrypt null` precedence, `/Prev` encryption inheritance, generation-specific `/Encrypt` selection, Standard `/P` bit decoding, unsigned/out-of-range `/P`, missing `/P`, duplicate `/P`, reserved-bit review, Standard authentication material, duplicate authentication material, `/Perms`, escaped auth keys, EncryptMetadata duplication, crypt-filter role/default/AuthEvent/key-length review, duplicate `/CF`, public-key recipient envelopes, encrypted associated-file redaction, DSS/signature review, or stream-filter `/Crypt` DecodeParms behavior. The bounded behavior is only duplicate selected trailer/xref-stream `/Encrypt` declarations before text extraction or permission import.

## Dependency closure

No new support component is needed. This reuses the native PHP object scanner, top-level dictionary parser, xref/trailer chain walker, metadata extractor, security preflight, text extraction encryption gate, and WordPress smoke renderer. Full Standard-handler decryption, password validation, CMS/PKCS#7 public-key permission decoding, permission enforcement, signing/signature validation, revocation checks, live OCR, Surya/Texify/Torch execution, PDFium rendering, and external PDF tools remain intentionally out of scope.
