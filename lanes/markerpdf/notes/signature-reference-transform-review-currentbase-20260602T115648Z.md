# Signature reference transform review, 2026-06-02

Micro-slice: `security-signature-encryption-boundary-currentbase-20260602T115648Z`

Source truth:

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF content through pdftext/PDFium-style extraction before model conversion; this native PHP lane keeps signature and permission features as deterministic review metadata unless a separate cryptographic component is activated.
- PDF signature dictionaries may carry `/Reference` entries whose `/TransformMethod` values include certification (`/DocMDP`), field modification (`/FieldMDP`), and usage-rights (`/UR` or `/UR3`) transforms. This slice maps the review boundary only.

Implemented behavior:

- `PdfAcroFormExtractor` now parses signature `/Reference` transform dictionaries with method-specific metadata.
- Existing `/DocMDP` output remains compatible while adding review-only execution flags.
- `/FieldMDP` transform params now expose `/Action`, named or object-referenced `/Fields`, included/excluded/all-field scope labels, and digest-method presence without exposing raw digest bytes.
- `/UR` and `/UR3` transform params now expose document, form, signature, annotation, and embedded-file usage-right categories plus optional review message without enforcing rights.
- `PdfSecurityPreflight` now surfaces reference-transform counts and methods on the full preflight report and on each signature row.
- Added a WordPress smoke that imports visible signed text while emitting FieldMDP and UR3 review metadata without signature validation, signing, rights enforcement, Python/model execution, or external PDF tools.

Non-overlap:

- This does not repeat accepted encrypted-PDF fail-closed extraction, Standard permission preflight, encrypted metadata priority, DocMDP catalog permissions, signature seed-value `/SV`, signature `/Lock`, signed lock-state propagation, or ByteRange coverage classification. The new behavior is limited to signature `/Reference` transform review metadata.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `1 test files, 138 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed with `2 test files, 640 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with `64 test files, 3807 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-signature-reference-transform-review.php` emitted `plain_text_imported=true`, `reference_transform_methods=["FieldMDP","UR3"]`, `field_mdp_field_names=["invoice.total","internal.notes"]`, `usage_right_categories=["document","form","signature","annotations","embedded_files"]`, `raw_digest_value_exposed=false`, `raw_signature_contents_exposed=false`, `executes_rights_enforcement=false`, `executes_signature_validation=false`, `executes_signing=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -l` passed for `PdfAcroFormExtractor.php`, `PdfSecurityPreflight.php`, `PdfSecurityPreflightTest.php`, and `wordpress-pdf-signature-reference-transform-review.php`.
- `git diff --check -- lanes/markerpdf` passed.

Status delta:

- Behavior tests move `491 -> 492`.
- Mapped focused markerPDF/PDF semantics move `339 -> 340 / 78`.

Dependency closure:

- No new support component is needed. This reuses the native PDF object/dictionary parser, AcroForm signature parser, security preflight report path, and visible text extractor.
- Full password validation, PDF decryption, CMS/PKCS#7 signature validation, timestamp validation, rights enforcement, signing, and trust-chain handling remain out of scope. Activating those would require a separate native cryptographic/signature support component with password, certificate, detached-signature, timestamp, and tamper-evidence fixtures.
