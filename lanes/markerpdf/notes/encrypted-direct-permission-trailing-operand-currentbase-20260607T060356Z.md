# markerPDF encrypted direct permission trailing operand current-base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260607T060356Z`
Session: `port-dev-markerpdf-encrypted-preflight-20260607T060356Z`
Base accepted HEAD: `1cde769ee126890582dd00219dbdc8eac5224735`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream searchable-PDF import
gets parser text before OCR/model conversion, so the native no-GPU PHP lane must
block encrypted visible text until decryption is available and treat malformed
encryption permission operands as review-only metadata.

PDF dictionary values are single objects. A Standard encryption dictionary
entry like `/P -44 6 0 R` is not a valid permission integer declaration for
import preflight: the first integer must not be trusted while an extra top-level
operand follows it. This mirrors the already accepted `/Encrypt` trailing
operand boundary, but applies it to the Standard permission word itself.

## Behavior

`PdfMetadataExtractor::standardPermissionWordDeclarationReview()` now reads
Standard `/P` entries through top-level value reviews. Direct permission words
with trailing non-key operands are reported as
`permission_word_trailing_operand_review`; `standard_permissions` is not
materialized unless the declaration review is `well_formed_standard_permissions`.

The security preflight now reports:

- `source=standard_security_handler_malformed_permissions`
- `policy=permissions_malformed_blocked_without_decryption`
- `content_extraction_boundary=blocked_encrypted_permissions_malformed`
- `permission_hex=null`
- `copy_or_extract_allowed=null`
- `permission_bits_reliable=false`

Encrypted page text, raw owner/user authentication material, decoy permission
bytes, decryption, permission enforcement, Python/model execution, and external
PDF tools remain excluded.

## Evidence

Red-first probe before source edits:

```bash
php -r '... /P -44 6 0 R ...'
```

Result: the malformed direct `/P` operand decoded as
`source=standard_security_handler_permissions`, `permission_hex=FFFFFFD4`, and
`review_reasons=["encrypted_document","encrypted_text_extraction_blocked","copy_or_extract_allowed_but_decryption_required"]`.

Focused passing command after source edits:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionDirectTrailingOperandCurrentBaseTest.php
```

Result: `1 test files, 144 assertions, 0 failures`.

Adjacent encrypted-permission regression command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCommentedIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateParameterCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionDuplicateTrailerEncryptCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCompositeOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php
```

Result: `6 test files, 651 assertions, 0 failures`.

Encrypted-permission family regression command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php
```

Result: `50 test files, 4806 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-encrypted-direct-permission-trailing-operand-currentbase.php
```

Result: emitted `encrypted_text_blocked=true`,
`permission_source=standard_security_handler_malformed_permissions`,
`permission_policy=permissions_malformed_blocked_without_decryption`,
`permission_hex=null`, `copy_or_extract_allowed=null`,
`permission_bits_reliable=false`,
`entry_statuses=["permission_word_trailing_operand_review"]`,
`trailing_operand_shape=indirect_reference`, `trailing_operand_preview=6 0 R`,
and all decryption/permission-enforcement/model/external-tool flags false.

## Non-Overlap

This does not repeat accepted encrypted fail-closed text extraction, direct
well-formed Standard permission decoding, unsigned/out-of-range `/P`, plus
integers, missing `/P`, duplicate `/P`, selected-entry `/P`, indirect trailing
`/P` object bodies, composite `/P` operands, comments around indirect scalar
operands, malformed reserved bits, duplicate Standard handler parameters,
explicit malformed parameter operands, authentication material review, crypt
filter method/AuthEvent/key-length checks, public-key recipient envelopes,
trailer `/Encrypt` precedence, duplicate/trailing `/Encrypt`, encrypted
attachment redaction, DSS/signature/DocMDP/FieldMDP review, OCR/model
execution, or stream-filter `/Crypt` behavior. The bounded new behavior is
direct top-level trailing operands on the Standard `/P` permission word.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner,
dictionary value-review parser, Standard permission-word decoder, metadata
extractor, security preflight, text extractor, and WordPress smoke path. Full
Standard-handler decryption, password validation, permission enforcement,
public-key CMS/PKCS#7 decoding, live `pdftext`, PDFium rendering, OCR/model
execution, and external PDF tools remain intentionally out of scope.

## Follow-Up

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
and converter behavior: fonts, CMaps, stream filters, xref repair, metadata,
annotations, forms, page geometry, image/filter metadata, and supplied-boundary
table/equation handoffs.
