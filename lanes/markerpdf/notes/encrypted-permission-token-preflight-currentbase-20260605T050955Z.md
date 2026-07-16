# markerPDF encrypted permission token preflight current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T050955Z`

Base accepted HEAD: `de3977d12ff1d59781a2e8ab61ab27832f03b3f6`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The no-GPU PHP lane maps the upstream searchable-PDF parser boundary before OCR/layout/model stages. For encrypted PDFs, the Standard security-handler `/P` permission word must be interpreted only when it is a usable integer operand; raw non-integer tokens and unresolved indirect references are malformed permission declarations, not absent permissions or copy/extract grants.

## Behavior

- `PdfSecurityPreflight` now separates "Standard `/P` was declared" from "Standard permission bits were decoded".
- Raw-but-unusable `/P` operands such as `/P (copy-ok)` and `/P 99 0 R` now fail closed as `standard_security_handler_malformed_permissions`.
- The preflight exposes the existing declaration review (`permission_word_non_integer_review` or `permission_word_unresolved_reference`), keeps `permission_hex=null`, masks bit-derived copy/accessibility grants, and leaves encrypted text blocked without decryption, permission enforcement, Python/models, or external PDF tools.

## Red-first evidence

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Before the source change, the two new cases failed after 190 assertions because both malformed operands were reported as `encryption_permissions_unknown`.

## Focused verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Result:

```text
1 test files, 292 assertions, 0 failures
```

Adjacent encrypted/security sweep:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
```

Result:

```text
17 test files, 1678 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-token-preflight-currentbase.php
```

Result: emitted `permission_word_non_integer` and `permission_word_unresolved_reference` review reasons, `policy=permissions_malformed_blocked_without_decryption`, `permission_bits_reliable=false`, `copy_or_extract_allowed=null`, `raw_material_exposed=false`, and all decryption/permission-enforcement/model/external-tool flags false.

## Non-overlap

This does not repeat accepted Standard permission bit decoding, unsigned 32-bit normalization, out-of-range `/P` handling, duplicate `/P` conflict handling, reserved-bit malformed review, Standard `/V`/`/R`/top-level `/Length` parameter review, revision-6 `/Perms` readiness, indirect valid `/P` resolution, public-key recipient envelopes, crypt-filter method/AuthEvent/key-length review, encrypted associated-file metadata redaction, xref `/Encrypt` precedence, or signature ByteRange/DSS/DocMDP review. The bounded behavior is only malformed raw Standard `/P` operands that cannot yield decoded permission bits.

## Dependency closure

No new support component is needed. This reuses native PDF dictionary parsing, indirect scalar resolution, Standard permission declaration review, encrypted text blocking, and existing security preflight reporting. Full decryption/password validation, permission enforcement, live OCR, Surya/Texify/Torch model execution, PDFium rendering, and external PDF tools remain intentionally out of scope for the current no-GPU markerPDF lane.
