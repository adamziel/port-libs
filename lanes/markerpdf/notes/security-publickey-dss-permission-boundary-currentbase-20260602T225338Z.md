# markerPDF public-key DSS permission boundary current-base

Micro-slice: `security-publickey-dss-permission-boundary-currentbase`

Base accepted HEAD: `46dcbc383630b2d55e601d02ab9f1a9bd647b8e2`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF conversion through pdftext/PDFium-style extraction; encrypted content remains blocked unless a security handler can actually authorize and decrypt it.
- PDF public-key security handlers store permissions in PKCS#7 recipient envelopes. For `adbe.pkcs7.s5`, active recipient lists come from the crypt filters selected by `/StmF`, `/StrF`, and `/EFF`; legacy top-level `/Recipients` are not selected for S5.
- PAdES/DSS validation material and signature `/Reference` transforms are review metadata for validation and usage-right inspection. This lane does not parse CMS, match private keys, decrypt content, validate signatures, check revocation, build trust chains, enforce usage rights, or execute PDF actions.

## Implemented

- `PdfSecurityPreflight` now emits `public_key_dss_permission_boundary_review`.
- The new review composes already parsed facts into one WordPress/import boundary:
  - selected and unselected public-key recipient permission envelopes;
  - S5 legacy top-level recipient exclusion;
  - selected `/StmF`, `/StrF`, and `/EFF` crypt-filter names;
  - DSS validation stream and VRI signature-match counts;
  - DSS-linked FieldMDP/UR3 signature permission transform methods;
  - explicit decision `blocked_public_key_dss_permission_review_only`.
- Encrypted text import remains blocked, and DSS/signature usage-right metadata cannot grant text import without private-key recipient decoding and decryption.
- Added `PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php` and WordPress smoke `wordpress-pdf-publickey-dss-permission-boundary-currentbase.php`.

## Non-Overlap

This does not repeat accepted public-key recipient inventory, selected-vs-unselected public-key DSS review, legacy top-level recipient selection, DSS stream hashing, DSS VRI-to-signature correlation, DSS reference-transform review, DSS certificate/action permission context, Standard permission/authentication review, encrypted ByteRange review, or action execution review.

The bounded new behavior is the composed permission boundary tying public-key recipient envelopes, DSS validation material, and signature permission transforms together without granting import authorization.

## Verification

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` - passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php` - passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-publickey-dss-permission-boundary-currentbase.php` - passed.
- First focused run exposed one current-source contract correction: DSS validation streams are de-duplicated, so the boundary count is `2` unique streams rather than `4` global-plus-VRI references.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyDssPermissionBoundaryCurrentBaseTest.php` - passed, 1 test file / 77 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssSignatureReferenceTransformCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php` - passed, 5 test files / 767 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-publickey-dss-permission-boundary-currentbase.php` - emitted `text_blocked=true`, `boundary_present=true`, `boundary_decision=blocked_public_key_dss_permission_review_only`, `permission_policy=public_key_recipient_permissions_blocked_without_private_key`, `selected_recipient_count=2`, `unselected_recipient_count=2`, selected filters `DefaultCryptFilter` and `EmbeddedFiles`, unselected filter `UnusedRights`, `dss_signature_match_count=1`, `signature_permission_methods=["FieldMDP","UR3"]`, `raw_security_material_exposed=false`, and all CMS/decryption/permission-enforcement/rights-enforcement/signature-validation/revocation/trust-chain/external-tool execution flags false.

## Status Delta

- Focused markerPDF behavior tests move `930 -> 932` pass / `0` fail.
- WordPress scenario coverage moves `930 -> 932`.
- Mapped semantics remain `654 / 78`; this is a composed current-base boundary over already mapped public-key recipient, DSS, and signature-transform primitives.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, encryption dictionary parsing, public-key recipient inventory, DSS stream summarization, AcroForm signature parsing, and the existing encrypted-text fail-closed gate.

Full CMS/PKCS#7 parsing, private-key matching, recipient permission decoding, Standard/public-key decryption, permission enforcement, signature validation, revocation checks, trust-chain validation, signing, and PDF action execution remain out of scope. Activating those requires a bounded native cryptographic/decryption/signature component with password, public-key recipient, decrypted stream/string, signed/tampered, and trust/revocation fixtures.
