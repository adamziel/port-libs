# markerPDF public-key recipient permission review

Micro-slice: `security-publickey-recipient-permission-review-currentbase-20260602T1629Z`

## Source truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `pdftext.extraction.dictionary_output(...)` and pypdfium page text extraction before the model/layout pipeline, so encrypted content must remain fail-closed until native decryption exists:
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
  - https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py
- Adobe PDF Reference 1.7 section 3.5 says public-key encryption handlers use PKCS#7 recipient objects. `/Recipients` is required for `adbe.pkcs7.s3` and `adbe.pkcs7.s4`; for `adbe.pkcs7.s5`, recipient lists live in crypt-filter dictionaries. Recipient envelopes include key material and access permissions, and repeated recipients use the first matching list.
  - https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.7old.pdf

## Implemented behavior

- `PdfMetadataExtractor` now extracts sanitized public-key recipient review metadata:
  - top-level encryption dictionary `/Recipients` arrays for `adbe.pkcs7.s3`/`s4` style documents;
  - crypt-filter `/Recipients` arrays for `adbe.pkcs7.s5`;
  - direct and indirect byte-string recipient entries;
  - recipient counts, total bytes, SHA-256 hashes, source policy, and crypt-filter names.
- `PdfSecurityPreflight` now distinguishes public-key recipient-envelope permissions from both missing Standard `/P` permissions and unsupported non-Standard `/P` words.
- Public-key recipient permissions report `policy=public_key_recipient_permissions_blocked_without_private_key` and `status=public_key_recipient_permissions_undecoded_review`.
- Encrypted text extraction remains blocked. Recipient bytes, certificates, owner/user keys, CMS payloads, decryption, permission enforcement, signing, signature validation, Python/model execution, and external PDF tools remain blocked.
- Added a WordPress smoke that emits only sanitized recipient policy/count/hash metadata before Gutenberg import.

## Non-overlap

This does not repeat accepted encrypted-PDF fail-closed extraction, Standard encryption `/P` permission flag extraction, malformed Standard reserved-bit review, unsupported non-Standard handler `/P` review, encrypted metadata priority, signature ByteRange review, DocMDP/FieldMDP/UR3 transforms, DSS review, signature `/SV`, or signature `/Lock` parsing.

The bounded behavior is only public-key recipient-envelope permission review metadata for legacy `/Recipients` and crypt-filter recipient lists.

## Focused verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  - `1 test files, 331 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
  - `1 test files, 324 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - `3 test files, 1233 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-publickey-recipient-permission-review-currentbase.php`
  - emitted `recipient_count=2`, `permission_policy=public_key_recipient_permissions_blocked_without_private_key`, `handler_status=public_key_recipient_permissions_undecoded_review`, `recipient_source_policy=crypt_filter_recipients`, `recipient_bytes_exposed=false`, `executes_cms_parse=false`, `executes_decryption=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l` passed for changed PHP files:
  - `lanes/markerpdf/src/PdfMetadataExtractor.php`
  - `lanes/markerpdf/src/PdfSecurityPreflight.php`
  - `lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
  - `lanes/markerpdf/tests/PdfSecurityPreflightTest.php`
  - `lanes/markerpdf/examples/wordpress-pdf-publickey-recipient-permission-review-currentbase.php`
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Status delta

- Behavior tests move `558 -> 560` pass / `0` fail.
- Mapped markerPDF/PDF semantics move `399 -> 400 / 78`.

## Dependency closure

No new support component is needed for this slice. It reuses the native PDF object/trailer parser, stream-safe encrypted text boundary, crypt-filter dictionary parsing, and security preflight report path.

Full CMS/PKCS#7 parsing, private-key matching, recipient permission decoding, Standard security-handler decryption, public-key decryption, permission enforcement, signing, signature validation, and trust-chain handling remain out of scope. Activating them requires a separate bounded native cryptography/decryption component with password fixtures, public-key recipient fixtures, decrypted stream/string fixtures, and signature-validation evidence.
