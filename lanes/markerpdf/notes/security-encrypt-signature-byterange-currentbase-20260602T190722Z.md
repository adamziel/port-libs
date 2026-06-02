# markerPDF encrypted signature ByteRange current-base review

Micro-slice: `security-encrypt-signature-byterange-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through pdftext/PDFium-style extraction and this native lane keeps encrypted content fail-closed unless a future bounded decryption component is activated.
- PDF signature semantics use `/ByteRange` to identify the signed byte ranges and exclude the signature `/Contents` bytes from the digest. A structurally valid ByteRange is still signature metadata, not decryption permission and not native import authorization for encrypted content.
- Existing accepted markerPDF security slices already cover encrypted fail-closed extraction, Standard permission flags, permission-handler review, public-key recipients, DSS stream hashing, DocMDP/DSS digest matching, post-signature action ByteRange coverage, and DSS multi-signature correlation.

## Implementation

- `PdfSecurityPreflight` now reports top-level `signature_byte_range_count`, `valid_signature_byte_range_count`, and `encrypted_signature_byte_range_review_count`.
- Encrypted signed PDFs with `/ByteRange` metadata now get an `encrypted_signature_byte_range_review` row that records valid/invalid ByteRange status, field names, permission policy, and content-extraction boundary while preserving:
  - `content_extraction_allowed=false`
  - `byte_range_does_not_grant_import=true`
  - `executes_decryption=false`
  - `executes_signature_validation=false`
  - `executes_signing=false`
  - raw signature/key material exposure flags false
- Security review reasons now include `encrypted_signature_byte_range_present` for encrypted documents that carry signature ByteRange metadata.
- Added a WordPress smoke for an encrypted, copy-permitted signed PDF whose ByteRange covers the signed revision but whose text remains blocked.

## Non-Overlap

This does not repeat the standalone ByteRange classifier, invalid encrypted ByteRange preflight, DSS stream hashing, DocMDP/DSS review, permission-handler reliability review, public-key recipient handling, Launch/URI action review, or post-signature action byte-offset coverage. The new behavior is specifically the encrypted-document correlation that says valid signature ByteRange metadata remains review-only and does not unlock text import.

## Verification

- Baseline before this slice: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed `1 test files, 475 assertions, 0 failures`.
- Focused behavior: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed `2 test files, 543 assertions, 0 failures`.
- Adjacent security gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php` passed `6 test files, 774 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-security-encrypt-signature-byterange-currentbase.php` emitted `encrypted_text_blocked=true`, `valid_signature_byte_range_count=1`, `byte_range_status=covers_file_except_signature_contents`, `byte_range_does_not_grant_import=true`, and decryption/signature-validation/signing/external-tool flags false.
- PHP lint passed for `PdfSecurityPreflight.php`, `PdfSecurityPreflightTest.php`, `PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php`, and `wordpress-pdf-security-encrypt-signature-byterange-currentbase.php`.
- JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused markerPDF behavior tests move `679 -> 681` pass / `0` fail with two new PASS cases.
- Mapped markerPDF/PDF semantics expected move `493 -> 494 / 78`.

## Dependency Closure

No new support component is needed. This reuses native metadata parsing, AcroForm signature dictionary traversal, ByteRange structural review, encrypted-text fail-closed extraction, and security preflight reporting.

Full Standard security-handler decryption, password validation, CMS/PKCS#7 signature validation, X.509 parsing, revocation checking, trust-chain validation, signing, and permission enforcement remain out of scope. Activating those requires a separate native cryptographic/decryption component with password-protected stream/string fixtures, signed/tampered PDFs, public-key recipient fixtures, and trust/revocation validation evidence.
