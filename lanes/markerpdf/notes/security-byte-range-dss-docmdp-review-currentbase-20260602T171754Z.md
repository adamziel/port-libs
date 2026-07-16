# markerPDF ByteRange/DSS/DocMDP current-base review

Micro-slice: `security-byte-range-dss-docmdp-review-currentbase-20260602T171754Z`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through pdftext/PDFium-style extraction and does not validate signatures, build trust chains, run revocation checks, or execute PDF actions during ordinary conversion.
- PDF signature review semantics keep `/ByteRange` as the signed byte-coverage boundary, `/Contents` as CMS/signature bytes, catalog `/Perms /DocMDP` as certification-permission intent, and catalog `/DSS /VRI` as long-term-validation material keyed by the signature contents digest.
- This PHP slice maps that boundary as sanitized review metadata for WordPress import. It does not perform CMS/PKCS#7 validation, certificate parsing, OCSP/CRL validation, timestamp validation, signing, Python/model execution, or external PDF tooling.

## Implementation

- `PdfAcroFormExtractor` now records sanitized signature `/Contents` digest metadata (`sha1`, `sha256`, byte count, raw-bytes-exposed false) for hex, literal, and indirect contents values.
- `PdfSecurityPreflight` now emits `signature_security_review_count`, top-level `signature_security_reviews`, and per-signature `signature_security_review` rows that combine:
  - ByteRange validity/status and signature-gap coverage.
  - DocMDP certification permission level, label, allowed changes, and transform parameter version.
  - DSS `/VRI` match status against the signature contents SHA-1/SHA-256 digest.
  - Matched VRI Cert/OCSP/CRL/timestamp stream counts and SHA-256 hashes only.
- Added `examples/wordpress-pdf-security-byte-range-dss-docmdp-review-currentbase.php` to demonstrate visible Gutenberg text import plus review-only signature metadata without exposing synthetic signature, certificate, OCSP, or timestamp payload bytes.

## Non-Overlap

This does not repeat accepted encrypted-PDF fail-closed preflight, Standard permission metadata, permission-handler review, public-key recipient review, standalone signature ByteRange classification, standalone catalog DSS extraction, FieldMDP/UR3 reference transform review, AcroForm `/SV` seed constraints, `/Lock` field scope, or standalone DocMDP catalog permission extraction. The new behavior is the combined ByteRange + DSS VRI digest match + DocMDP certification review row.

## Verification

- Red-first check after adding the focused assertion: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` failed on missing `signature_security_review`, `signature_security_reviews`, and `signature_security_review_count`.
- `php -l lanes/markerpdf/src/PdfAcroFormExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-byte-range-dss-docmdp-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed: 1 test file, 408 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php` passed: 1 test file, 740 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-security-byte-range-dss-docmdp-review-currentbase.php` emitted `byte_range_status=covers_file_except_signature_contents`, `dss_vri_match_status=matched_signature_contents_sha1`, `doc_mdp_permission_label=form_fill_templates_signatures`, `dss_vri_validation_stream_count=3`, `raw_review_material_exposed=false`, `executes_signature_validation=false`, `executes_revocation_check=false`, `executes_trust_chain_validation=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

## Status Delta

- Focused markerPDF behavior tests move 593 -> 594 PASS cases.
- Focused assertions in `PdfSecurityPreflightTest.php` move 371 red-first attempted assertions to 408 passing assertions after implementation.
- WordPress scenarios move 593 -> 594 with the new security ByteRange/DSS/DocMDP review smoke.
- Mapped source/dependency semantics expected move: 428 -> 429 / 78.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, AcroForm signature parsing, DSS stream summarization, stream-filter decoding, and security preflight reporting. Full CMS/PKCS#7 signature validation, X.509 parsing, OCSP/CRL validation, RFC 3161 timestamp validation, trust-store handling, signing, and permission enforcement remain out of scope and would require a separate native cryptographic validation component with signed/tampered PDF fixtures before activation.
