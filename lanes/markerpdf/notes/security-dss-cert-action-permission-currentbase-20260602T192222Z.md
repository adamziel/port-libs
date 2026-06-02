# markerPDF DSS certificate action-permission review

Micro-slice: `security-dss-cert-action-permission-currentbase`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level PDF parsing to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text to pypdfium. The native PHP lane therefore keeps PDF security material as review metadata outside visible WordPress paragraphs.
- PDF signature review semantics keep catalog `/DSS` certificates/OCSP/CRL streams as validation material, `/VRI` keys as signature-content digest references, `/Reference /TransformMethod /FieldMDP` as field-modification permission metadata, `/UR3` as usage-right metadata, and `/OpenAction`, annotation `/A`, annotation `/AA`, and `/Next` dictionaries as viewer-action payloads. This slice reviews those boundaries only.

## Implemented behavior

- `PdfSecurityPreflight` now adds `document_action_security_review.dss_certificate_review` with unique DSS certificate object numbers/hashes, global and VRI certificate counts, matched VRI signature counts, and explicit non-execution flags.
- `PdfSecurityPreflight` now adds `document_action_security_review.signature_permission_transform_review` with FieldMDP action labels, field scopes, included/excluded fields, UR/UR3 right categories, and right counts.
- Every document action row now carries the DSS certificate and permission-transform context, while retaining existing URI/Launch/SubmitForm safety classification and signature ByteRange coverage metadata.
- Added `PdfSecurityDssCertActionPermissionCurrentBaseTest.php` and `wordpress-pdf-security-dss-cert-action-permission-currentbase.php`.

## Non-overlap

This does not repeat standalone DSS extraction, DSS VRI-to-signature matching, ByteRange/DSS/DocMDP correlation, post-signature action byte-range review, FieldMDP/UR3 parsing, Launch/URI action classification, AcroForm permission action review, public-key recipient permissions, or encrypted-text preflight. The new behavior is the combined action-review context: DSS certificate hashes plus FieldMDP/UR3 permission metadata on document action rows.

## Verification

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php` failed on missing `signature_permission_transform_review`, `dss_certificate_review`, and `dss_certificate_action_permission_review`.
- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-dss-cert-action-permission-currentbase.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php` passed: `1 test files, 110 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php` passed: `6 test files, 830 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurity*.php` passed: `8 test files, 928 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-security-dss-cert-action-permission-currentbase.php` emitted `plain_text_imported=true`, `action_count=4`, `unsafe_action_count=3`, `dss_certificate_count=2`, `dss_vri_signature_match_count=1`, `signature_permission_transform_methods=["FieldMDP","UR3"]`, `raw_security_material_exposed=false`, and execution flags false.
- `git diff --check -- lanes/markerpdf` passed.

## Status delta

- Behavior tests move `705 -> 707` pass / `0` fail.
- WordPress scenarios move `705 -> 706`.
- Mapped markerPDF/PDF semantics move `508 -> 509 / 78`.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, AcroForm signature parser, DSS stream summarizer, action walker, security preflight report path, and visible text extractor.

Full CMS/PKCS#7 validation, X.509 parsing, trust-chain validation, OCSP/CRL validation, timestamp-token validation, rights enforcement, permission enforcement, PDF action execution, signing, decryption, Python/model execution, pdftext/pypdfium execution, and external PDF tools remain out of scope and require a separate bounded native cryptographic validation/action component before activation.
