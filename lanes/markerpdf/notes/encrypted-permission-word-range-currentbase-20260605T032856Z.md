# markerPDF encrypted permission word range current base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260605T032856Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium-style opening before conversion. This native PHP lane keeps encrypted content fail-closed unless a separate native decryption component is activated.
- PDF Standard security-handler `/P` permission words are 32-bit permission words. The lane already normalizes signed and unsigned 32-bit decimal spellings; this slice adds the explicit boundary for values outside both the signed and unsigned 32-bit domains.

## Implemented behavior

- `PdfMetadataExtractor::standardPermissionMetadata()` now marks Standard `/P` values outside `[-2147483648, 4294967295]` as `permission_word_out_of_range_review`.
- Out-of-range permission words no longer produce synthetic permission hex, signed/unsigned projections, allow/deny permission names, permission-bit rows, or print-quality labels.
- `PdfSecurityPreflight` carries the range metadata through `permission_preflight`, `permission_handler_review`, and top-level `encryption` review metadata.
- Import stays fail-closed with `policy=permissions_malformed_blocked_without_decryption` and `review_reasons` includes `permission_word_out_of_range`.
- Encrypted text, owner/user validation bytes, decryption, permission enforcement, Python/model work, and external PDF tooling remain excluded.

## Non-overlap

This does not repeat encrypted fail-closed extraction, direct signed `/P -44` preflight, unsigned 32-bit `/P` normalization, revision-gated permission-bit applicability, malformed reserved-bit review, duplicate `/P` review, Standard authentication material readiness, crypt-filter role review, public-key recipient envelope review, DSS/signature permission review, or xref `/Prev` Encrypt precedence.

The bounded behavior is specifically Standard permission-word range preflight for values that cannot be represented as a signed or unsigned 32-bit permission word.

## Focused evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php` failed with both out-of-range fixtures reported as `permission_word_reserved_bits_malformed` after `1 test file, 6 assertions, 2 failures`.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php` passed with `1 test file, 114 assertions, 0 failures`.
- Adjacent security/metadata regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionWordRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `6 test files, 1722 assertions, 0 failures`.
- Encrypted-permission family run: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php` passed with `11 test files, 793 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-word-range-currentbase.php` emitted `text_blocked=true`, `policy=permissions_malformed_blocked_without_decryption`, `review_reasons=["encrypted_document","encrypted_text_extraction_blocked","permission_word_out_of_range"]`, `permission_word_range_valid=false`, `permission_word_range_status=permission_word_out_of_range_review`, `permission_hex=null`, `copy_or_extract_allowed=null`, `permission_bits_reliable=false`, `handler_status=permission_word_out_of_range_review`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- Behavior tests move `1353 -> 1355` pass / `0` fail for the two focused TestRunner cases.
- WordPress scenarios move `1298 -> 1299` for the added smoke coverage.
- No upstream denominator-total change is claimed.

## Dependency closure

No new support component is needed. This reuses the native PDF object/trailer parser, Standard encryption dictionary parser, encrypted-text fail-closed gate, permission metadata path, and security preflight report path.

Full password validation, Standard security-handler decryption, permission authentication from `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, revocation checks, and trust-chain validation remain out of scope for this no-GPU/no-model markerPDF slice.
