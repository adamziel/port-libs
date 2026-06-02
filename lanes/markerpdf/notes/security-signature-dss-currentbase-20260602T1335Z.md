# markerPDF signature DSS current-base review

Micro-slice: `security-signature-dss-currentbase-20260602T1335Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through pypdfium/pdftext text extraction and block rendering; it does not validate signatures, revocation status, or certificate chains during ordinary document conversion.
- PDFBox `ShowSignature` treats catalog `/DSS` as a Document Security Store used for signature validation and inspects `/Certs`, `/OCSPs`, and `/CRLs` stream arrays without making them visible document text.
- iText `LtvVerification` describes PAdES LTV validation material as OCSP, CRL, and certificate bytes added for a signature and merged into DSS/VRI dictionaries.

## Implementation

- Added `PdfDocumentSecurityStoreExtractor` for catalog `/DSS` review metadata.
- `PdfSecurityPreflight` now reports `document_security_store`, `document_security_store_count`, DSS review reasons, and blocked validation operations.
- DSS global `/Certs`, `/OCSPs`, `/CRLs`, VRI `/Cert` `/OCSP` `/CRL`, `/TU`, and `/TS` timestamp-token streams are summarized by object number, decoded length, filter names, subtype, and SHA-256 only.
- Raw certificate, OCSP, CRL, timestamp token, signature contents, owner/user key, and digest bytes remain suppressed; no decryption, signature validation, revocation checking, trust-chain validation, signing, Python/model execution, or external PDF tooling is executed.

## Non-overlap

- This does not repeat accepted Standard encryption permission metadata, encrypted metadata priority, signature ByteRange preflight, DocMDP/FieldMDP/UR3 reference transforms, AcroForm `/SV` seed-value constraints, `/Lock` field scope, or signed lock-state behavior. The new behavior is limited to catalog-level DSS long-term validation material.

## Verification

- Red-first check after adding the DSS assertion: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` failed on missing `document_security_store` and missing `document_security_store_present`.
- `php -l lanes/markerpdf/src/PdfDocumentSecurityStoreExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-signature-dss-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `3 test files, 1001 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-signature-dss-currentbase.php` emitted `dss_present=true`, `validation_stream_count=4`, VRI key `ABCDEF1234`, `review_required_signature_metadata`, blocked `revocation_check` and `trust_chain_validation`, `raw_validation_bytes_exposed=false`, `executes_signature_validation=false`, `executes_revocation_check=false`, `executes_trust_chain_validation=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed for review metadata. This reuses native PDF object, dictionary, array, stream-filter, and security-preflight parsing. Full CMS/PKCS#7, X.509 certificate parsing, OCSP/CRL validation, trust-store handling, timestamp-token validation, and signing remain out of scope and would require a separate native cryptography/validation component with signed PDF fixtures before activation.
