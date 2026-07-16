# markerPDF security ByteRange DSS revision current-base

Micro-slice: `security-byte-range-dss-revision-currentbase`

Base accepted HEAD: `897b69532c5e798e5593546ffafd7329358413f2`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through pdftext/PDFium extraction and model/layout post-processing; it does not perform PDF signature validation, revocation checking, trust-chain validation, signing, decryption, or PDF action execution during ordinary conversion.
- The PDF signature `/ByteRange` identifies signed byte segments around `/Contents`. In incrementally updated PDFs, a signature can cover an earlier signed revision while later validation material such as catalog `/DSS` and `/VRI` dictionaries is appended for long-term-validation review.
- This lane already maps DSS validation streams as sanitized review metadata. This slice adds the current-base revision boundary between a signed prior revision and appended matching DSS/VRI validation material.

## Implementation

- `PdfSecurityPreflight` now adds `signature_byte_range_revision_review` with signed-revision validity, signed revision end/length, current tail bytes, and current-vs-prior revision status.
- Existing `byte_range.status`, `valid`, import decision, and action byte-range coverage behavior are preserved. Prior-revision signatures still require review when they do not cover the current full file.
- `document_security_store_signature_review` now annotates matched VRI rows with signed-revision coverage rows and top-level counts/statuses for VRI entries appended after a signed revision.
- Raw signature contents, certificate, OCSP, CRL, and timestamp-token bytes remain suppressed; no cryptographic signature validation, revocation check, trust-chain validation, signing, decryption, Python/model execution, or external PDF tooling is run.
- Added WordPress smoke `wordpress-pdf-security-byte-range-dss-revision-currentbase.php` for a two-revision PDF where the first revision contains signed page text and the later revision appends matching DSS/VRI validation material.

## Non-overlap

This does not repeat accepted encrypted-PDF text blocking, Standard/public-key permission parsing, direct or indirect DSS stream hashing, signature contents digest matching, DocMDP/FieldMDP/UR3 transform review, Launch/URI/action security review, encrypted valid ByteRange review, or post-signature action byte-range review. The new behavior is the review-only signed-revision extent and appended DSS/VRI coverage metadata.

## Verification

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityByteRangeDssRevisionCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-byte-range-dss-revision-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityByteRangeDssRevisionCurrentBaseTest.php` passed with `1 test files, 60 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityEncryptSignatureByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityByteRangeDssRevisionCurrentBaseTest.php` passed with `9 test files, 988 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-security-byte-range-dss-revision-currentbase.php` emitted `plain_text_imported=true`, `byte_range_revision_status=covers_prior_revision_except_signature_contents`, `prior_revision_signature_count=1`, `dss_vri_revision_status=vri_after_signed_revision`, `raw_review_material_exposed=false`, and all validation/revocation/trust-chain/Python/external-tool execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

## Status delta

- Behavior tests move `742 -> 744` pass / `0` fail.
- New focused assertions: `60`.
- WordPress scenarios move `742 -> 744`.

## Dependency closure

No new support component is needed. This reuses the existing native PDF object span scanner, AcroForm signature metadata parser, DSS stream summarizer, and security preflight review paths.

Full CMS/PKCS#7 parsing, X.509 chain validation, OCSP/CRL validation, timestamp-token validation, trust-store integration, decryption, permission enforcement, signing, and external PDF validator integration remain out of scope. Activating them would require a separate bounded native cryptography/validation component with signed incremental PDF fixtures and trust-material oracles.
