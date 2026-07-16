# Encrypted Permissions Revision Bit Preflight Current Base, 2026-06-04

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260604T213119Z`

Base accepted HEAD: `8ad8bbc5f21ff1d8f312f5c8f2c4491394b44dbe`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium-style extraction before OCR/layout/model conversion. The native PHP lane therefore keeps encrypted content fail-closed unless a native decryption component is explicitly activated.
- PDF Standard security-handler `/P` permission words are bit fields whose meaning depends on the security-handler revision. Bits 3-6 apply to revision 2; bits 9-12 apply only to revision 3 and later. Permission bits can be present but not applicable for an older revision, and that must not be reported as an import grant.

## Implemented Behavior

- `PdfMetadataExtractor::standardPermissionMetadata()` now emits `permission_bits` rows for all tracked Standard permission flags, including bit number, mask, minimum revision, effective revision, raw bit state, applicability, allowed/denied booleans, and status.
- The review includes `applicable_permission_names`, `not_applicable_permission_names`, `permission_bit_review_count`, and `permission_bit_statuses`.
- `PdfSecurityPreflight` propagates the same review rows under top-level `encryption`, `permission_preflight`, and `permission_handler_review` for Standard handlers only.
- A revision-2 encrypted Standard PDF with `/P -44` now reports copy/extract as allowed only after decryption, while `fill_form_fields`, `extract_for_accessibility`, `assemble_document`, and `high_quality_print` are explicitly `not_applicable_for_revision`.
- A revision-4 encrypted Standard PDF with the same 32-bit permission pattern reports all eight tracked permission flags as applicable.
- Encrypted text extraction, permission enforcement, decryption, Python/model execution, external PDF tools, and raw owner/user key exposure remain disabled.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct Standard `/P` preflight, unsigned permission-word normalization, indirect encryption operand resolution, malformed reserved-bit review, unsupported handler review, Standard authentication digest review, public-key recipient envelope inventory, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file metadata redaction, or crypt-filter StmF/StrF/EFF content-role preflight.

The bounded new behavior is specifically revision-aware Standard permission-bit applicability metadata before current-base encrypted import decisions.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php` failed with `1 test files, 5 assertions, 2 failures` because `applicable_permission_names` and `permission_bits` were missing.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php` passed with `1 test files, 88 assertions, 0 failures`.
- Focused encrypted-permission regression set: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `5 test files, 753 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-bit-preflight-currentbase.php` emitted `encrypted_text_blocked=true`, `permission_bit_review_count=8`, `permission_bit_statuses=["allowed_by_permission_bit","denied_by_permission_bit","not_applicable_for_revision"]`, `applicable_permission_names=["print","modify_contents","copy_or_extract","add_or_modify_annotations"]`, `not_applicable_permission_names=["fill_form_fields","extract_for_accessibility","assemble_document","high_quality_print"]`, `executes_decryption=false`, `executes_permission_enforcement=false`, and no Python/model/external PDF tool execution.
- PHP lint: `php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/src/PdfSecurityPreflight.php && php -l lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-permission-bit-preflight-currentbase.php` passed.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decode with `JSON_THROW_ON_ERROR`.
- Whitespace check: `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused PHP behavior tests move `1093 -> 1095` PASS cases.
- WordPress scenarios move `1093 -> 1095`.
- Mapped focused markerPDF/PDF semantics add `pdfEncryptedPermissionRevisionBitPreflightCurrentBaseBehaviors=1`; no upstream denominator-total change is claimed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, Standard encryption dictionary parser, Standard permission word normalization, encrypted text fail-closed gate, and security preflight report path.

Full Standard security-handler decryption, password validation, encrypted stream/string decryption, permission authentication from `/Perms`, public-key CMS/PKCS#7 permission decoding, permission enforcement, signing, signature validation, revocation checking, trust-chain validation, OCR/model execution, and external PDF tooling remain out of scope for this no-GPU native parser slice.
