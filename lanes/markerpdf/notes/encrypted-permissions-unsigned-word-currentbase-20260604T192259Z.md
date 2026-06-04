# markerPDF encrypted permissions unsigned word current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260604T192259Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` opens PDF documents through pdftext/PDFium-style handling before conversion. The native PHP lane keeps encrypted content fail-closed unless a separate native decryption component is activated.
- PDF Standard security-handler permissions are a signed 32-bit `/P` permission word. Some PDF writers serialize the same 32-bit bit pattern as an unsigned decimal, so review metadata must normalize that value before WordPress import policy decisions.

## Implemented behavior

- `PdfMetadataExtractor::standardPermissionMetadata()` now normalizes unsigned 32-bit Standard `/P` values, preserving both signed and unsigned forms.
- `PdfSecurityPreflight` now exposes normalized permission signed/unsigned values, permission-word form, and unsigned-decimal normalization state in:
  - top-level `encryption` review metadata;
  - `permission_preflight`;
  - `permission_handler_review`.
- A Standard encrypted PDF with `/P 4294967252` now reports signed `-44`, unsigned `4294967252`, hex `FFFFFFD4`, reliable well-formed permission bits, and `copy_extract_allowed_after_decryption` while native text extraction remains blocked.

## Non-overlap

This does not repeat accepted encrypted fail-closed extraction, direct signed `/P -44` preflight, indirect encryption operand resolution, malformed reserved-bit review, unsupported handler review, Standard authentication digest review, public-key recipient envelope inventory, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, or signature ByteRange/DSS/DocMDP/FieldMDP review.

The bounded new behavior is specifically unsigned 32-bit Standard `/P` permission-word normalization before current-base encrypted import decisions.

## Focused evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php` failed before the implementation with missing unsigned permission metadata (`Expected: 4294967252`, `Actual: NULL`) after `1 test files, 8 assertions, 1 failures`.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php` passed with `1 test files, 44 assertions, 0 failures`.
- Focused security/metadata regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php` passed with `6 test files, 1596 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-unsigned-permission-preflight-currentbase.php` emitted `encrypted_text_blocked=true`, `declared_permission_word=4294967252`, `permission_word_form=unsigned_decimal`, `permission_signed=-44`, `permission_unsigned=4294967252`, `permission_hex=FFFFFFD4`, `policy=copy_extract_allowed_after_decryption`, `permission_bits_reliable=true`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Behavior tests move `1082 -> 1084` pass / `0` fail for the two added focused TestRunner cases.
- WordPress scenarios move `1082 -> 1084` for the focused test cases and smoke coverage.

## Dependency closure

No new support component is needed. This reuses the native PDF object/trailer parser, Standard encryption dictionary parser, permission metadata path, encrypted-text fail-closed gate, and security preflight report path.

Full password validation, Standard security-handler decryption, permission authentication from `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, and trust-chain validation remain out of scope for this no-GPU/no-model markerPDF slice.
