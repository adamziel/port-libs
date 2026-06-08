# Encrypted Crypt-Filter Inherited Key-Length Preflight

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T191537Z`

Accepted base: `d057fc34a05090199b091f73d0a8aa3124240396`

## Scope

This patch covers one Standard encrypted-PDF security preflight behavior:

- crypt-filter dictionaries using encrypted methods (`/V2`, `/AESV2`, `/AESV3`) may omit their local `/Length`;
- when the top-level Standard security-handler `/Length` is present and converts to a method-supported byte count, markerPDF now reports that inherited key length in encryption metadata and crypt-filter content-role preflight rows;
- malformed or explicit local crypt-filter `/Length` entries continue through the existing local declaration and key-length fail-closed review.

No decryption, permission enforcement, OCR, model execution, raster rendering, pypdfium/PIL, or external PDF tooling is performed.

## Red-First Evidence

Before the implementation change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterDefaultLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL inherits top-level Standard length for AESV2 crypt filters that omit local Length
Values are not identical
Expected: 16
Actual: NULL
FAIL inherits top-level Standard length for AESV3 crypt filters that omit local Length
Values are not identical
Expected: 32
Actual: NULL

1 test files, 20 assertions, 2 failures
```

## Passing Evidence

Focused behavior test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterDefaultLengthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS inherits top-level Standard length for AESV2 crypt filters that omit local Length
PASS inherits top-level Standard length for AESV3 crypt filters that omit local Length

1 test files, 127 assertions, 0 failures
```

Adjacent encrypted crypt-filter regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterDefaultLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDefaultCryptFilterMethodCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterMethodGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS strips comments around indirect Standard permission integers before encrypted preflight
PASS inherits top-level Standard length for AESV2 crypt filters that omit local Length
PASS inherits top-level Standard length for AESV3 crypt filters that omit local Length
PASS fails closed when encrypted document crypt filters declare invalid key lengths
PASS fails closed when Standard AES256 document roles select legacy crypt-filter methods
PASS fails closed when Standard revision four document roles select AES256 crypt-filter methods
PASS summarizes Standard crypt-filter content roles before encrypted permission import preflight
PASS treats omitted crypt-filter CFM as default None before encrypted permission preflight

6 test files, 453 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-encrypted-crypt-filter-default-length-currentbase.php
exit 0; reports plain_text_blocked=true, key_length_bytes=16, key_length_source=standard_security_handler_length_inherited, key_length_statuses=["crypt_filter_key_length_supported"], executes_decryption=false, executes_permission_enforcement=false, executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat:

- invalid explicit crypt-filter `/Length` review;
- default `/CFM /None` behavior;
- crypt-filter method generation compatibility review;
- malformed Standard `/V`, `/R`, `/Length`, `/P`, authentication material, or `/Perms` operand handling;
- public-key recipient selection or live decryption.

The new behavior is limited to Standard encrypted crypt filters with no local `/Length` and a valid inherited top-level Standard key length.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF dictionary parser, Standard security-handler metadata extraction, and crypt-filter content-role preflight review. Remaining no-GPU exclusions stay unchanged: live OCR, Surya/Texify/Torch/model execution, raster visual table recognition, pypdfium/PIL, and exact upstream model benchmark parity are intentionally out of scope.
