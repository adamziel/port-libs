# Encryption permission preflight boundary, 2026-06-02

Micro-slice: `encryption-permission-preflight-boundary-currentbase-20260602T113109Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through pdftext/PDFium-style document handling before model conversion. This native lane keeps encrypted-content handling at a deterministic preflight/review boundary unless a separate native decryption component is activated.
- The PDF 1.7 security handler model stores encryption state in trailer or xref-stream `/Encrypt` dictionaries and stores Standard security-handler user-access permissions in `/P`. Those permission bits can say copy/extract is allowed or denied, but they do not mean plaintext is available when no password validation/decryption has run.
- Relevant sources: `https://github.com/sddai/markerPDF/tree/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` and `https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf`.

Implemented behavior:

- `PdfSecurityPreflight::analyze()` now adds a sanitized `permission_preflight` report beside the existing encryption and signature review metadata.
- Encrypted PDFs with Standard `/P` copy/extract allowed now report `policy=copy_extract_allowed_after_decryption` and `content_extraction_boundary=blocked_until_decryption_password_available`; native text extraction remains blocked.
- Encrypted PDFs with no Standard permission metadata now report `policy=permissions_unknown_blocked_without_decryption` and do not get mislabeled as `copy_or_extract_denied`.
- Encrypted PDFs with copy/extract denied keep the accepted `copy_or_extract_denied` review reason.
- The new WordPress smoke exposes copy-allowed and unknown-permission decisions while confirming no owner/user keys, signature contents, decryption, Python/models, or external PDF tools execute.

Non-overlap:

- This does not repeat accepted encrypted-PDF fail-closed text extraction, Standard encryption dictionary metadata, encrypted metadata source priority, security preflight signature ByteRange classification, DocMDP permissions, signature `/SV`, or signature `/Lock` parsing. The new behavior is limited to permission-aware import preflight policy around encrypted PDFs before decryption exists.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 89 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with `3 test files, 845 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-encryption-permission-preflight.php` emitted `copy_allowed_policy=copy_extract_allowed_after_decryption`, `copy_allowed_permission_hex=FFFFFFD4`, `copy_allowed_text_blocked=true`, `unknown_permission_policy=permissions_unknown_blocked_without_decryption`, `unknown_permission_source=encryption_dictionary_without_standard_permissions`, `raw_key_material_exposed=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-encryption-permission-preflight.php` passed.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move `485 -> 487`.
- Mapped focused markerPDF/PDF semantics move `333 -> 334 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF metadata parser, encrypted-PDF fail-closed text extraction gate, Standard permission metadata, and security preflight report path.
- Full password validation, Standard security-handler decryption, public-key security handler support, CMS/PKCS#7 signature validation, signing, trust-chain handling, and encrypted content extraction remain out of scope. Activating those requires a separate native cryptographic/decryption component with password fixtures, encrypted stream/string fixtures, permission enforcement fixtures, and signature-validation evidence.
