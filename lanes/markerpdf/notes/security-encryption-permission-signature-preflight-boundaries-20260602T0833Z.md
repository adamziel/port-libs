# Security encryption/signature preflight boundaries, 2026-06-02

Micro-slice: `security-encryption-permission-signature-preflight-boundaries-20260602T082558Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps conversion behind pdftext/PDFium-style PDF preflight and does not decrypt protected content, validate CMS signatures, sign documents, or execute AcroForm actions in this native lane.
- Adobe's PDF 1.7 reference describes Standard security-handler permissions in the encryption dictionary and signature dictionaries with `/ByteRange` plus `/Contents`; this slice implements only deterministic preflight/review metadata for those boundaries.
- Acrobat security-handler docs also treat `/Encrypt` as the trailer gate for security-handler authorization/decryption; this native lane remains fail-closed unless a future bounded decryption component is explicitly activated.

Implemented behavior:

- Added `PdfSecurityPreflight::analyze()` to compose existing `PdfMetadataExtractor` encryption metadata and `PdfAcroFormExtractor` signature metadata into one import preflight report.
- Encrypted PDFs now report `content_extraction_allowed=false`, `text_extraction_policy=blocked_without_decryption`, Standard permission allow/deny labels, copy/extract status, and `raw_owner_user_keys_exposed=false`.
- Signature fields now get review-only ByteRange boundary classification: shape, segment coverage, file-bound checks, signature-contents gap detection, and invalid/out-of-bounds quarantine status without exposing `/Contents` bytes or validating the CMS payload.
- The preflight explicitly reports `executes_decryption=false`, `executes_signature_validation=false`, `executes_signing=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- Added a WordPress smoke that blocks encrypted text import while emitting review metadata for permissions and invalid signature byte ranges.

Non-overlap:

- This does not repeat accepted encrypted-PDF text fail-closed extraction, Standard encryption dictionary metadata, DocMDP permissions, signature seed-value `/SV`, signature `/Lock` dictionary parsing, AcroForm `/CO`, or signed lock-state propagation. It adds the import preflight decision boundary and ByteRange file-coverage classifier that consumes those accepted metadata surfaces.

Focused verification:

- Red-first before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` failed with missing `PortLibs\MarkerPDF\PdfSecurityPreflight` in both new tests, `1 test files, 0 assertions, 2 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 44 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `3 test files, 564 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-security-preflight-boundaries.php` emitted `encrypted_text_blocked=true`, `import_decision=block_encrypted_content_review_security_metadata`, `permission_hex=FFFFFFC0`, `copy_or_extract_allowed=false`, `invalid_signature_byte_range_count=1`, `raw_owner_user_keys_exposed=false`, and no decryption, signature validation, signing, Python/model, or external PDF-tool execution.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-preflight-boundaries.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- `phpPass`: `438 -> 440`.
- Mapped focused markerPDF/PDF semantics: `291 -> 292 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF metadata parser, encrypted-PDF fail-closed extraction gate, AcroForm/signature dictionary traversal, and WordPress review metadata paths.
- Full decryption, password validation, CMS/PKCS#7 signature validation, signing, and trust-chain handling remain out of scope. Activating those would require a separate native cryptographic/decryption support component with fixtures for Standard security-handler passwords and detached signature validation.

Sources:

- https://github.com/sddai/markerPDF/tree/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34
- https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf
- https://opensource.adobe.com/dc-acrobat-sdk-docs/library/plugin/Plugins_Security.html
