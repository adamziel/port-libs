# Security encryption permission-handler review current base, 2026-06-02

Micro-slice: `security-encryption-permission-handler-review-currentbase-20260602T1608Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through pdftext/PDFium before model conversion (`marker/pdf/extract_text.py`) and opens documents through `pypdfium2.PdfDocument` in `marker/convert.py`. This native lane keeps encrypted content fail-closed unless a future native decryption component is activated.
- PDF 1.7 / ISO 32000-1 Standard security-handler permissions use `/P` as a 32-bit permission word. Bits outside the defined permission bits are reserved; low bits must remain clear and high reserved bits must be set, so malformed `/P` values need review instead of being treated as reliable import grants.
- Relevant sources: `https://github.com/sddai/markerPDF/tree/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`, `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`, and `https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf`.

Implemented behavior:

- `PdfMetadataExtractor` now records Standard permission word reserved-bit review metadata: effective revision, expected set/clear masks, well-formed status, and precise reserved-bit violations.
- `PdfSecurityPreflight` now emits top-level `permission_handler_review` and nested `permission_preflight.permission_handler_review`.
- Malformed Standard `/P` words, such as a positive `00000010` word with copy/extract set but reserved high bits clear, report `policy=permissions_malformed_blocked_without_decryption`, `status=malformed_reserved_bits_review`, and `permission_bits_reliable=false`.
- Non-Standard handlers carrying a misleading top-level `/P`, such as `/Filter /PublicKey`, report `policy=permissions_unsupported_handler_blocked_without_decryption` and `status=unsupported_security_handler_permissions_review`; Standard permission allow/deny labels are not promoted to preflight allow/deny fields.
- Encrypted text extraction remains blocked in all cases. The new WordPress smoke exposes sanitized policy/status fields while suppressing owner/user keys, recipient bytes, decrypted content, and permission enforcement.

Non-overlap:

- This does not repeat accepted encrypted-PDF fail-closed extraction, Standard encryption dictionary metadata, encrypted metadata source priority, the earlier copy-allowed-vs-decryption preflight, signature ByteRange classification, DocMDP/FieldMDP/UR3 transforms, DSS review, signature `/SV`, or signature `/Lock` parsing.
- The bounded behavior is specifically permission-handler reliability review for malformed Standard reserved bits and unsupported non-Standard handler permission words.

Focused verification:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` failed with missing `permission_handler_review`; malformed and unsupported handler fixtures were both reported as `copy_extract_allowed_after_decryption`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 236 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `1 test files, 308 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with `3 test files, 1122 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-encryption-permission-handler-review-currentbase.php` emitted `malformed_policy=permissions_malformed_blocked_without_decryption`, `malformed_handler_status=malformed_reserved_bits_review`, `malformed_reserved_violations=["reserved_bits_7_8_clear","reserved_bits_13_32_clear"]`, `unsupported_policy=permissions_unsupported_handler_blocked_without_decryption`, `unsupported_handler_status=unsupported_security_handler_permissions_review`, `malformed_text_blocked=true`, `unsupported_text_blocked=true`, `raw_key_material_exposed=false`, `recipient_bytes_exposed=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l` passed for `PdfMetadataExtractor.php`, `PdfSecurityPreflight.php`, `PdfMetadataExtractorTest.php`, `PdfSecurityPreflightTest.php`, and `wordpress-pdf-encryption-permission-handler-review-currentbase.php`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move `534 -> 536`.
- Mapped focused markerPDF/PDF semantics move `381 -> 382 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF object/trailer parser, Standard permission metadata parser, encrypted-text fail-closed gate, and security preflight report path.
- Full password validation, Standard security-handler decryption, public-key recipient permission decoding, CMS/PKCS#7 signature validation, permission enforcement, signing, and trust-chain handling remain out of scope. Activating those requires a separate native cryptographic/decryption support component with password fixtures, encrypted stream/string fixtures, public-key recipient fixtures, and signature-validation evidence.
