# markerPDF DSS Action ByteRange current-base review

Micro-slice: `security-dss-action-byte-range-currentbase-20260602T181423Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF parsing/text extraction to pdftext/PDFium-style readers and does not execute PDF actions, validate CMS signatures, build trust chains, run OCSP/CRL checks, or perform signing during normal conversion.
- PDF signature semantics use `/ByteRange` as the signed byte coverage boundary, `/Contents` as the excluded CMS/signature byte string, catalog `/DSS /VRI` as long-term-validation review material keyed by signature digest, and action dictionaries such as `/OpenAction`, `/A`, `/AA`, and `/Next` as viewer-executable payloads.
- This slice maps the current-base security boundary where DSS-backed certifying signature metadata is present, but action objects are appended after the signed byte range. WordPress import must surface those action objects as post-signature review metadata only.

## Implementation

- `PdfSecurityPreflight` now computes indirect PDF object byte spans and annotates document action rows with per-signature byte-range coverage:
  - `signature_byte_range_coverage_status`
  - `covered_by_all_signature_byte_ranges`
  - `outside_any_signature_byte_range`
  - `signature_byte_range_signed_coverage_count`
  - `signature_byte_range_unsigned_coverage_count`
  - `signature_byte_range_reviews`
- `document_action_security_review` now reports `action_byte_range_review_count`, `post_signature_action_count`, `unsigned_action_byte_range_count`, `post_signature_action_objects`, `action_byte_range_statuses`, and `has_post_signature_actions`.
- Security preflight review reasons now include `post_signature_pdf_actions_present` when action objects sit outside a declared signature byte range.
- Added `PdfSecurityDssActionByteRangeCurrentBaseTest.php` with a DSS/VRI + DocMDP certified fixture whose `/OpenAction` Launch/URI action objects are appended after the signed revision.
- Added `examples/wordpress-pdf-security-dss-action-byte-range-currentbase.php` to show visible paragraph import plus review-only post-signature action metadata without exposing signature, certificate, OCSP, or action execution.

## Non-Overlap

This does not repeat accepted encrypted-PDF fail-closed preflight, standalone ByteRange validation, DSS stream hashing, ByteRange/DSS/DocMDP digest matching, FieldMDP/UR3 transform parsing, public-key recipient permission envelopes, permission-handler review, catalog OpenAction chain walking, Launch/URI action safety classification, or signature field/action state summaries. The new behavior is specifically byte-offset coverage review for indirect document action objects relative to signature ByteRange segments.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php` failed before implementation with missing `post_signature_pdf_actions_present`.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-dss-action-byte-range-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php` passed: 1 test file, 56 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed: 1 test file, 475 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php` passed: 1 test file, 44 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-security-dss-action-byte-range-currentbase.php` emitted `post_signature_action_count=2`, `unsigned_action_byte_range_count=2`, `first_action_coverage_status=outside_all_signature_byte_ranges`, `first_action_signature_coverage_status=outside_signed_revision`, `raw_review_material_exposed=false`, and execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused markerPDF behavior tests move 633 -> 635 PASS cases.
- Focused assertions add 56 assertions in the new security current-base test.
- Mapped semantics expected move 461 -> 462 / 78.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, AcroForm signature parsing, DSS review metadata, outline/action review extraction, and existing text extraction. Full CMS/PKCS#7 validation, X.509 chain building, OCSP/CRL validation, RFC 3161 timestamp validation, trust-store policy, permission enforcement, and PDF action execution remain out of scope and require a separate native cryptographic validation/action-sandbox component with signed/tampered fixtures before activation.
