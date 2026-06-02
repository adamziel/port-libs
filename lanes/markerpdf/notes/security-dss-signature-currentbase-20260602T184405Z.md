# markerPDF DSS signature current-base review

Micro-slice: `security-dss-signature-currentbase`
Session: `port-dev-markerpdf-security39pdf-20260602T1843Z`
Base accepted HEAD: `4bfec4c2ed04ec45b69266408311f6827e291bfb`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` describes Marker as a PDF-to-Markdown extraction pipeline; its conversion path opens PDFs with `pypdfium2`, extracts page text with `pdftext.extraction.dictionary_output`, then runs layout/OCR/table/image/Markdown post-processing.
- The upstream path is an extraction pipeline, not a signature validation or signing engine. This native PHP lane therefore keeps DSS, VRI, certificate, OCSP, CRL, timestamp, and signature bytes as review-only metadata during WordPress import.
- Relevant PDF signature semantics for this slice: catalog `/DSS /VRI` entries are keyed by digests of signature `/Contents` bytes and carry validation-related streams. The PHP port now correlates VRI keys back to signature contents digests without parsing CMS, validating cryptographic signatures, building trust chains, checking revocation, validating timestamps, or exposing raw validation bytes.

## Patch

- `PdfSecurityPreflight` now emits `document_security_store_signature_review`.
- The review summarizes DSS VRI rows matched to signature contents SHA-1/SHA-256 digests, unmatched/orphan VRI keys, matched field names/signature objects, validation stream hashes, timestamp updates, and review-only execution flags.
- Added `PdfSecurityDssSignatureCurrentBaseTest.php` with a two-signature DSS fixture: one VRI row keyed by approval signature SHA-1, one keyed by timestamp signature SHA-256, and one orphan VRI row.
- Added `wordpress-pdf-security-dss-signature-currentbase.php` to show visible Gutenberg paragraph import plus review-only DSS signature correlation.

## Non-overlap

This does not repeat accepted encrypted-PDF fail-closed preflight, standalone signature ByteRange classification, DSS stream hashing, ByteRange/DSS/DocMDP digest matching, FieldMDP/UR3 transform parsing, public-key recipient permission envelopes, permission-handler review, Launch/URI action security review, or post-signature action byte-range review. The new behavior is document-level DSS VRI-to-signature digest correlation across multiple signatures, including orphan VRI rows.

## Verification

- Red-first focused check before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php` failed on missing `document_security_store_signature_review` while the visible text isolation case passed.
- Focused new test after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php` passed `1 test files, 55 assertions, 0 failures` with 2 PASS lines.
- Focused security regression: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php` passed `3 test files, 575 assertions, 0 failures`.
- Combined focused security command: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php` passed `4 test files, 630 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-security-dss-signature-currentbase.php` emitted `plain_text_imported=true`, `matched_vri_count=2`, `unmatched_vri_count=1`, `matched_signature_objects=[30,31]`, `vri_match_statuses=["matched_signature_contents_sha1","matched_signature_contents_sha256","no_matching_signature_contents_digest"]`, `raw_review_material_exposed=false`, and all signature/revocation/trust-chain/external-tool execution flags false.
- PHP lint passed for `lanes/markerpdf/src/PdfSecurityPreflight.php`, `lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-security-dss-signature-currentbase.php`.
- `php -r '$json=file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status delta

- Focused markerPDF PASS lines move `648 -> 650` locally.
- Focused assertions added: `55`.
- `lane-status.json` is updated for the pending current-base handoff.
- Root harness status: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses native PDF object parsing, AcroForm signature parsing, DSS stream summarization, stream-filter decoding, and security preflight reporting. Full CMS/PKCS#7 parsing, X.509 path validation, OCSP/CRL validation, RFC 3161 timestamp validation, trust-store handling, signing, and permission enforcement remain out of scope and would require a separate native cryptographic validation component with signed/tampered fixtures before activation.
