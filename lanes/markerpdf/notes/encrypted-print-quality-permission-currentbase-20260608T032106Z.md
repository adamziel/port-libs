# Encrypted Print Quality Permission Current Base

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260608T032106Z`
Base: `a7522664de4f48f76386e7674a6e6f31f30ae868`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- In the no-GPU native PHP scope, encrypted PDF security state is preflight metadata for the importer. Encrypted page text and authentication material remain blocked until decryption/password validation is implemented.
- PDF Standard security permissions distinguish basic printing from high-quality printing. A document can allow print while denying high-quality print, which should be reported as a low-resolution print permission boundary rather than collapsed into the generic copy/extract decision.

## Behavior

`PdfSecurityPreflight` now emits `standard_permission_print_quality_review` in the top-level report, `permission_preflight`, and `encryption_review`:

- reliable Standard permission bits with print allowed and high-quality print denied are summarized as `print_quality=low_resolution`;
- the review records `print_allowed=true`, `high_quality_print_allowed=false`, and `limited_to_low_resolution=true`;
- unauthenticated encrypted documents remain `native_import_allowed_now=false` with boundary `blocked_until_password_validation_and_permission_authentication`;
- password validation, permission enforcement, and decryption are explicitly not executed by this review path;
- owner/user material, raw permission bytes, and encrypted page payload text stay out of visible WordPress content.

The new WordPress smoke covers a Standard encrypted PDF with `/P -2092` (`0xFFFFF7D4`), where basic printing and copying are set but high-quality printing is denied.

## Red First

Before the source patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintQualityReviewCurrentBaseTest.php`

Result: `1 test files, 30 assertions, 1 failures`

Failure: the new `standard_permission_print_quality_review` keys were missing from the preflight payloads.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintQualityReviewCurrentBaseTest.php`

Result: `1 test files, 60 assertions, 0 failures`

Adjacent encrypted permission/security family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintQualityReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionPrintDependencyCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionOperationPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionRevisionBitCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionsPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`

Result: `7 test files, 1126 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-encrypted-print-quality-currentbase.php`

Result: passed. The smoke reports `encrypted_text_blocked=true`, `permission_policy=copy_extract_allowed_after_decryption`, `copy_or_extract_allowed=true`, `native_text_extraction_allowed_now=false`, `print_quality=low_resolution`, `print_review_status=low_resolution_print_pending_authentication`, `limited_to_low_resolution=true`, `print_permission_boundary=blocked_until_password_validation_and_permission_authentication`, `raw_material_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfEncryptedPermissionPrintQualityReviewCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-print-quality-currentbase.php` passed.

Status JSON:

- `php -r '$s=file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($s, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "OK\n";'` passed.

Required whitespace check:

`git diff --check -- lanes/markerpdf` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2923 -> 2925` from 2 new focused TestRunner PASS cases.
- `wordpressScenarios`: `2435 -> 2436` from the new WordPress smoke.
- Manifest mapped count: unchanged; this is an additive current-base security preflight behavior, not a new upstream inventory path.

## Non-Overlap

This does not repeat existing encrypted permission handling for malformed `/P`, reserved-bit reliability, unsigned/out-of-range operands, duplicate/missing `/P`, indirect operands, authentication trust summaries, Standard operation rows, print dependency status, `/Perms` digest review, crypt filters, trailer `/Encrypt` precedence, public-key recipient envelopes, encrypted metadata source priority, AcroForm permission actions, or signature ByteRange review.

The bounded behavior is only the sanitized Standard permission print-quality review that separates low-resolution print from high-quality print denial while preserving the existing encrypted-text import block.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF parser, `PdfSecurityPreflight` permission-bit decoding, `PdfMetadataExtractor`, `PdfTextExtractor`, focused PHP test harness, and WordPress smoke pattern. Full Standard decryption, password validation, `/Perms` authentication enforcement, public-key CMS handling, permission enforcement, signature validation, OCR, Surya/Texify/Torch/model workers, PDFium, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
