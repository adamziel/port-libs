# markerPDF DSS signature Reference transform current-base review

Micro-slice: `security-dss-signature-reference-transform-currentbase`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF import through `pdftext`/PDFium-style page text extraction (`marker/pdf/extract_text.py`) and ordinary conversion does not validate CMS signatures, build trust chains, enforce usage rights, sign PDFs, or execute PDF actions.
- PDF signature reference dictionaries use `/TransformMethod` plus transform parameters to describe DocMDP, FieldMDP, UR/UR3, or related object-digest/revision-comparison rules. Adobe PDF Reference 1.7 section 8.7 describes DocMDP and FieldMDP transform semantics and ByteRange-first validation boundaries.
- PAdES DSS/VRI semantics store validation-related information in catalog `/DSS` and `/VRI` rows keyed to signature digest material. This PHP port keeps those rows as review metadata only.

References:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf
- https://www.etsi.org/deliver/etsi_en/319100_319199/31914201/01.02.01_60/en_31914201v010201p.pdf

## Implementation

- `PdfSecurityPreflight` now threads matched signature `/Reference` transform metadata into catalog DSS VRI digest review rows.
- Each matched `document_security_store_signature_review.vri_signature_rows[]` row now exposes:
  - `signature_reference_transform_count`
  - `signature_reference_transform_methods`
  - sanitized `signature_reference_transform_rows`
- The top-level report now includes `document_security_store_signature_reference_transform_review`, `document_security_store_signature_reference_transform_count`, and `document_security_store_signature_reference_transform_methods`.
- The new review reports DocMDP permission labels/allowed changes, FieldMDP field scopes, and UR3 usage-right categories while keeping digest values, signature contents, certificates, OCSPs, and CRLs out of visible text and JSON.
- Added a WordPress smoke that imports the visible paragraph and emits review-only DSS/reference-transform metadata without validation, rights enforcement, signing, decryption, Python/model execution, or external PDF tooling.

## Non-Overlap

This does not repeat accepted encrypted-PDF fail-closed preflight, standalone ByteRange classification, DSS stream hashing, DSS multi-signature digest correlation, ByteRange/DSS/DocMDP digest review, post-signature action ByteRange review, FieldMDP target ByteRange coverage, DSS certificate/action permission review, public-key permission envelopes, or AcroForm seed-value/lock parsing. The new behavior is specifically the VRI-matched signature Reference transform summary attached to DSS rows.

## Verification

- Red-first focused test: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureReferenceTransformCurrentBaseTest.php` failed on missing `document_security_store_signature_reference_transform_review`, with the visible-text isolation case passing.
- Focused new test after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureReferenceTransformCurrentBaseTest.php` passed `1 test files, 69 assertions, 0 failures`.
- Adjacent security gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssSignatureReferenceTransformCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed `5 selected test files, 779 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-security-dss-signature-reference-transform-currentbase.php` emitted `plain_text_imported=true`, `dss_reference_transform_count=3`, `dss_reference_transform_methods=["DocMDP","FieldMDP","UR3"]`, `vri_with_reference_transform_count=1`, `raw_security_material_exposed=false`, and all validation/signing/external-tool execution flags false.
- PHP lint passed for changed PHP source/test/example files during focused verification.
- `jq empty` passed for changed markerPDF JSON status/manifest files.

## Status Delta

- Focused PASS lines move `811 -> 813`.
- Mapped markerPDF/PDF semantics move `570 -> 571 / 78`.
- Added WordPress smoke: `wordpress-pdf-security-dss-signature-reference-transform-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, AcroForm signature parser, ByteRange boundary classifier, DSS stream summarizer, and security preflight report path.

Full CMS/PKCS#7 parsing, X.509 trust-chain validation, OCSP/CRL validation, RFC 3161 timestamp validation, signature signing, decryption/password handling, and rights enforcement remain out of scope. Activating those requires a separate native cryptographic/decryption component with signed/tampered PDF fixtures, timestamp/revocation fixtures, permission-enforcement fixtures, and trust-store evidence.
