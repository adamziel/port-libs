# markerPDF certificate permission OpenAction current-base review

Micro-slice: `security-cert-permission-openaction-currentbase`

Base accepted HEAD: `c62aa9728114b98c1a1fb9c52de68e28a30a8476`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through pdftext/PDFium-style extraction before conversion. PDF viewer actions are outside the content extraction boundary and remain review metadata only.
- PDF signature permission semantics keep `/Perms /DocMDP`, signature `/Reference` transforms, FieldMDP locks, UR/UR3 usage-right declarations, and DSS certificate material as permission or validation review metadata. The native lane does not validate signatures, build certificate chains, enforce rights, or execute catalog `/OpenAction` chains.

## Implemented

- `PdfSecurityPreflight` now emits `cert_permission_open_action_review` inside `document_action_security_review`.
- Catalog OpenAction rows now carry:
  - `open_action_permission_status`;
  - DocMDP permission labels and allowed-change summaries;
  - FieldMDP action labels and field names;
  - UR/UR3 usage-right categories;
  - explicit false flags for action execution, rights enforcement, signature validation, and trust-chain validation.
- The focused fixture combines a certifying signature, `/Perms /DocMDP`, FieldMDP `/Action /All`, UR3 rights, DSS certificate review material, and a catalog OpenAction URI -> JavaScript/Launch chain. The page text imports, while action operands, signature bytes, and certificate bytes remain out of visible WordPress text.
- Added `examples/wordpress-pdf-security-cert-permission-openaction-currentbase.php` as a WordPress smoke for the same non-executing review boundary.

## Non-overlap

This does not repeat accepted encrypted fail-closed permission review, Standard permission digest/authentication review, public-key recipient permission envelopes, standalone Launch/URI action review, standalone OpenAction chain walking, DSS certificate/action permission correlation, ByteRange/DSS/DocMDP matching, AcroForm field action permission review, post-signature action byte-range review, or outline OpenAction page/thread context propagation.

The new behavior is the catalog OpenAction certification-permission classification: active OpenAction rows are explicitly marked review-only and not granted by DocMDP, FieldMDP, or UR3 declarations.

## Verification

- Focused test: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityCertPermissionOpenActionCurrentBaseTest.php` passed with `1 test files, 88 assertions, 0 failures` and 2 PASS lines.
- Adjacent gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityCertPermissionOpenActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutlineOpenActionThreadPieceInfoCurrentBaseTest.php` passed with `7 test files, 1142 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-security-cert-permission-openaction-currentbase.php` emitted `plain_text_imported=true`, `open_action_count=3`, `unsafe_open_action_count=2`, `open_action_permission_statuses=["catalog_open_action_review_only_not_granted_by_cert_permissions"]`, `doc_mdp_permission_labels=["no_changes"]`, `signature_permission_transform_methods=["DocMDP","FieldMDP","UR3"]`, `cert_permissions_grant_open_action_execution=false`, `raw_security_material_exposed=false`, and execution flags false.

## Status delta

- Behavior tests move `753 -> 755` pass / `0` fail.
- Mapped semantics move `537 -> 538 / 78`.
- WordPress smoke scenarios move `753 -> 754`.

## Dependency closure

No new support component is needed. This reuses the native PDF object parser, catalog OpenAction review walker, AcroForm signature transform extraction, DSS stream hashing, security preflight report path, and native text extraction.

Full CMS/PKCS#7 validation, X.509 parsing, trust-chain validation, revocation checks, timestamp validation, permission enforcement, action execution, JavaScript execution, decryption, pypdfium/pdftext execution, Python models, and external PDF tools remain out of scope and require separate native cryptographic validation and action-sandbox components with signed/tampered fixtures before activation.
