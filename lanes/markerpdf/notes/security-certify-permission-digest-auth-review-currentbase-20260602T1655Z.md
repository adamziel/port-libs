# markerPDF Standard permission digest/auth review

Micro-slice: `security-certify-permission-digest-auth-review-currentbase-20260602T1655Z`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `pdftext.extraction.dictionary_output(...)` and pypdfium-style document handling before model conversion. This lane keeps encrypted PDFs fail-closed unless a separate native decryption component is activated.
- The PDF Standard security handler stores password-derived authentication entries in `/O` and `/U`; revisions 5 and 6 additionally store encrypted file-key entries `/OE` and `/UE`, and encrypted permissions validation bytes in `/Perms`. Those entries can be inventoried for review, but password validation, permission authentication, decryption, and permission enforcement require cryptographic processing and are out of scope for this slice.

## Implemented behavior

- `PdfMetadataExtractor` now emits `standard_authentication_review` for `/Filter /Standard` encryption dictionaries.
- Revision 5/6 review metadata includes expected byte lengths for `/O`, `/U`, `/OE`, `/UE`, and `/Perms`; safe SHA-256 fingerprints; `AuthEvent` labels from crypt-filter dictionaries; and explicit false flags for password validation, permission authentication, permission enforcement, and decryption.
- `PdfSecurityPreflight` exposes the sanitized authentication review at top level, inside `permission_preflight`, inside `encryption`, and as summarized permission-handler fields.
- The WordPress smoke blocks encrypted text while emitting owner/user authentication-entry sizes, permission-digest status, and non-execution flags without leaking raw auth bytes or encrypted key bytes.

## Non-overlap

This does not repeat accepted encrypted-PDF fail-closed extraction, Standard `/P` permission allow/deny metadata, permission-handler reserved-bit review, copy-allowed preflight, public-key recipient envelope review, encrypted metadata source priority, signature ByteRange review, DocMDP/FieldMDP/UR3 transforms, DSS validation-stream hashing, trailer `/Encrypt` precedence, or full security-handler decryption.

The bounded behavior is only Standard security-handler authentication/digest input review for `/O`, `/U`, `/OE`, `/UE`, and `/Perms`.

## Focused evidence

- Pre-existing gap: focused metadata/security fixtures for this slice had no `standard_authentication_review` surface and permission-handler summaries had no Standard auth/digest fields before this implementation.
- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php` passed.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-certify-permission-digest-auth-review-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php` passed with `1 test files, 461 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 366 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with `3 test files, 1405 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-security-certify-permission-digest-auth-review-currentbase.php` passed and emitted `text_blocked=true`, `credential_entries_present=["owner_validation","user_validation","owner_encryption_key","user_encryption_key"]`, `permission_digest_status=permission_digest_ciphertext_review`, `raw_auth_material_exposed=false`, `password_validation_performed=false`, `permissions_authenticated=false`, `executes_decryption=false`, `executes_permission_enforcement=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status delta

- Behavior tests move `577 -> 579` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `414 -> 415 / 78`.

## Dependency closure

No new support component is needed. This reuses the native PDF object/trailer parser, encryption dictionary metadata parser, crypt-filter parser, encrypted-text fail-closed boundary, and security preflight report path.

Full password validation, Standard security-handler decryption, permission authentication from decrypted `/Perms`, encrypted stream/string decryption, public-key CMS parsing, permission enforcement, signing, signature validation, and trust-chain validation remain out of scope. Activating those requires a separate bounded native cryptography/decryption component with password fixtures, encrypted stream/string fixtures, revision 2/3/4/5/6 Standard-handler fixtures, `/Perms` validation fixtures, and signature-validation evidence.
