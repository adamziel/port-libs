# Encrypted permissions CFM None preflight current base, 2026-06-04

Micro-slice: `markerpdf-encrypted-permissions-preflight-current-base-20260604T230819Z`

Base accepted HEAD: `fbe3fc8556507be78718a50156c3db0ac6373d94`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through pdftext/PDFium before OCR/layout/model conversion. The native PHP lane therefore keeps encrypted page text fail-closed unless a native decryption component is explicitly activated.
- PDF crypt-filter semantics distinguish the selected crypt-filter name from the crypt-filter method. The predefined `Identity` filter and named crypt filters whose `/CFM` method is `/None` leave that selected string or stream class unencrypted. Encrypted methods such as `V2`, `AESV2`, and `AESV3` still require decryption, and missing/unknown methods stay fail-closed.

## Implemented Behavior

- `PdfSecurityPreflight` now classifies named crypt filters with `/CFM /None` as `identity_crypt_filter`, preserving the existing review-only behavior for the literal `/Identity` filter name and encrypted behavior for `V2`, `AESV2`, and `AESV3`.
- `PdfMetadataExtractor` now uses the same crypt-filter method classification when redacting encrypted associated FileSpec metadata:
  - `/StrF` pointing to `/CFM /None` preserves clear FileSpec strings as review metadata.
  - `/EFF` pointing to `/CFM /None` preserves embedded-file hash/checksum metadata.
  - encrypted page streams remain blocked when `/StmF` points to an encrypted filter.
- Raw owner/user key material, embedded payload bytes, encrypted page text, decryption, permission enforcement, Python/model work, and external PDF tools remain excluded.

## Non-Overlap

This does not repeat accepted encrypted fail-closed extraction, direct Standard `/P` permission preflight, unsigned permission-word normalization, indirect encryption operand resolution, Standard authentication digest review, public-key recipient envelope inventory, public-key DSS permission review, xref `/Prev` Encrypt inheritance, encrypted associated-file redaction for encrypted `StdCF`, or crypt-filter content-role review for literal `Identity`, encrypted, and missing filters.

The bounded new behavior is specifically named crypt-filter `/CFM /None` classification before encrypted WordPress import decisions.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php` failed before implementation with `1 test files, 14 assertions, 1 failures`; `/CFM /None` embedded-file payload policy was classified as `encrypted_filter_requires_decryption`.
- Focused new test after patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php` passed with `1 test files, 47 assertions, 0 failures`.
- Focused security/metadata regression: `php tools/run-tests.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterNoneCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionCryptFilterPreflightCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionIndirectOperandsCurrentBaseTest.php lanes/markerpdf/tests/PdfEncryptedPermissionUnsignedWordCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `6 test files, 765 assertions, 0 failures`.
- Security family: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurity*Test.php lanes/markerpdf/tests/PdfEncryptedPermission*CurrentBaseTest.php` passed with `20 test files, 1773 assertions, 0 failures`.
- Metadata family: `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadata*Encrypt*CurrentBaseTest.php` passed with `2 test files, 918 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-encrypted-cfm-none-permission-preflight-currentbase.php` emitted `text_blocked=true`, `embedded_file_payload_policy=identity_filter_review_only_payload_boundary`, `identity_role_names=["document_strings","embedded_file_streams"]`, `encrypted_role_names=["document_streams"]`, `attachment_filename_preserved=clear-cfm-none.xml`, `attachment_payload_hash_available=true`, `payload_content_included=false`, and all decryption/permission-enforcement/model/external-tool flags false.

## Status Delta

- Focused PHP behavior tests move `1100 -> 1101` PASS cases.
- WordPress scenarios move `1100 -> 1101`.
- No upstream denominator-total change is claimed.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, encryption dictionary parser, crypt-filter metadata parser, associated FileSpec metadata redaction path, encrypted text fail-closed gate, and security preflight report path.

Full Standard security-handler decryption, password validation, public-key CMS/PKCS#7 permission decoding, permission enforcement, signature validation, revocation checking, trust-chain validation, OCR/model execution, and external PDF tooling remain out of scope for this no-GPU native parser slice.
