# markerPDF security permission ByteRange FieldMDP current-base review

Micro-slice: `security-permission-byte-range-fieldmdp-currentbase`

Base accepted HEAD: `d1072c4d57f8bf8b55795755ca4bcc26ff531e74`

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes ordinary PDF conversion through pdftext/PDFium-style extraction and does not validate CMS signatures, enforce signature permissions, sign documents, decrypt PDFs, execute PDF actions, run Python models, or call external PDF tools during import.
- PDF signature `/ByteRange` values describe signed byte segments around signature `/Contents`. `/Reference /TransformMethod /FieldMDP` declares field-modification permission scope. This slice keeps both as review metadata and correlates FieldMDP target field objects/widgets with the signed-revision byte coverage.

## Implemented

- `PdfSecurityPreflight` now emits top-level `field_mdp_byte_range_review`.
- Each FieldMDP target row includes:
  - signature field/object and transform params/data object context;
  - action label and declared field names;
  - ByteRange status, signed-revision status, signed-revision end, and current-revision tail bytes;
  - field object and widget object coverage statuses from existing byte-span coverage logic;
  - covered, outside-signed-revision, unsigned-gap, unresolved, and non-enforcement flags.
- Added `PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php` with one FieldMDP-included field covered by the signed revision and one FieldMDP-included field object/widget appended after the signed ByteRange.
- Added `examples/wordpress-pdf-security-permission-byte-range-fieldmdp-currentbase.php` as a WordPress smoke for the same review-only boundary.

## Non-overlap

This does not repeat encrypted-PDF fail-closed permission review, Standard/public-key permission envelopes, standalone FieldMDP/UR3 parsing, AcroForm signed `/Lock` state, DSS VRI/signature digest matching, post-signature action ByteRange review, DSS certificate action-permission context, or catalog OpenAction certification-permission classification.

The new behavior is the FieldMDP target-field ByteRange coverage review: target fields and widgets are classified as signed-revision-covered or unsigned current-revision objects without enforcing permissions or validating signatures.

## Verification

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php` passed.
- `php -l lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-permission-byte-range-fieldmdp-currentbase.php` passed.
- Focused test: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php` passed with `1 test files, 76 assertions, 0 failures`.
- Adjacent security gate: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurity*.php` passed with `11 test files, 1152 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-security-permission-byte-range-fieldmdp-currentbase.php` emitted `plain_text_imported=true`, `import_decision=review_required_signature_boundary`, `field_mdp_target_field_count=2`, `field_mdp_target_not_covered_count=1`, statuses `["field_mdp_target_covered_by_signed_revision","field_mdp_target_outside_signed_revision"]`, `raw_security_material_exposed=false`, `field_permissions_enforced=false`, and execution flags false.

## Status delta

- Behavior tests move `787 -> 789` pass / `0` fail.
- New focused assertions: `76`.
- WordPress scenarios move `787 -> 788`.
- Mapped markerPDF/PDF semantics move `557 -> 558 / 78`.

## Dependency closure

No new support component is needed. This reuses the native PDF object-span scanner, AcroForm signature transform parser, security preflight report path, ByteRange coverage helpers, and visible text extractor.

Full CMS/PKCS#7 validation, X.509 parsing, trust-chain validation, revocation checks, timestamp validation, permission enforcement, signing, decryption, PDF action execution, Python/model execution, pdftext/pypdfium execution, and external PDF validation remain out of scope. Activating those requires a separate bounded native cryptographic validation and action-sandbox component with signed, tampered, and trusted-material fixtures.
