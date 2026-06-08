# MarkerPDF Encrypted Scalar Encrypt Operand Current Base

Slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T113545Z`

Base accepted HEAD: `07691b7f82738219a04899114d848d918330fb2f`

## Source Truth

Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through PDF parser/PDFium/pdftext boundaries before OCR/model stages. Under the current no-GPU markerPDF lane, encrypted PDFs remain native PHP security-preflight work: no password validation, decryption, permission enforcement, action execution, OCR, Python models, or external PDF tools.

For PDF trailer security metadata, a selected non-null `/Encrypt` value must resolve to an encryption dictionary. A scalar boolean/name/token operand is not an encryption dictionary and must fail closed before any stale `/Prev` Standard permission grant can be reused.

## Behavior

- `PdfSecurityPreflight::reviewReasons()` now preserves the specific `encrypt_dictionary_non_dictionary_operand` boundary when permissions are unknown because the current trailer selected a scalar `/Encrypt` operand.
- The import decision remains `block_encrypted_content_review_security_metadata`.
- Native text extraction remains blocked, stale previous `/P -44` permission grants stay suppressed, and stale `/O`/`/U` authentication bytes remain redacted from review output.
- Direct `/Encrypt << ... >>`, unresolved `/Encrypt 99 0 R`, and referenced `/Encrypt` object-body trailing operand boundaries continue to pass unchanged.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionScalarEncryptOperandCurrentBaseTest.php
```

Result: `1 test files, 6 assertions, 1 failures`; the new test expected `encrypt_dictionary_non_dictionary_operand` in `review_reasons`, but the preflight returned only `encrypted_document`, `encrypted_text_extraction_blocked`, and `encryption_permissions_unknown`.

## Focused Evidence

After the source, test, and smoke edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionScalarEncryptOperandCurrentBaseTest.php
```

Result: `1 test files, 84 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionScalarEncryptOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionMalformedTrailerEncryptCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDirectEncryptDictionaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionObjectBodyTrailingOperandCurrentBaseTest.php
```

Result: `4 test files, 288 assertions, 0 failures`.

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfEncryptedPermission.*CurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `66 test files, 6623 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-scalar-encrypt-operand-currentbase.php
```

Result: exits `0` and emits `plain_text_blocked=true`, `encrypted=true`, `review_reasons` including `encrypt_dictionary_non_dictionary_operand`, `permission_policy=permissions_unknown_blocked_without_decryption`, `stale_permission_grant_suppressed=true`, and all decryption/permission/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted direct `/Encrypt` dictionaries, unresolved `/Encrypt` references, duplicate `/Encrypt` entries, referenced Encrypt object-body trailing operands, malformed Standard permission words, duplicate `/P`, indirect `/P` generations, Standard auth material, public-key recipients, crypt-filter role/method/AuthEvent/Length boundaries, DSS/signature review, attachment redaction, OCR/model execution, PDFium rendering, or external PDF tools. The bounded behavior is only the current trailer scalar non-dictionary `/Encrypt` operand reason before stale `/Prev` permission fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP trailer/object scanner, encryption metadata extractor, security preflight, encrypted text guard, and WordPress smoke renderer. Full password validation, decryption, permission enforcement, public-key CMS decoding, signing/signature validation, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope.

Root harness status: not run - isolated micro-slice.
