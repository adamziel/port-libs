# markerPDF security permission DSS action-chain current-base

## Source truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes conversion through pdftext/PDFium-style page extraction and does not execute PDF actions, validate signatures, enforce certification permissions, run revocation checks, or build certificate trust chains during ordinary document import.
- PDF security review semantics keep `/DSS` validation streams, signature `/Reference` transforms (`DocMDP`, `FieldMDP`, `UR3`), and `/A`/`/Next` action chains as review-only metadata. If an action object is appended outside a valid signature ByteRange, WordPress import should see a combined review row rather than treating DSS evidence or permission transforms as an action-execution grant.

## Implementation

`PdfSecurityPreflight` now emits `permission_dss_action_chain_review` and extends `dss_certificate_action_permission_review` with:

- post-signature action counts, unsafe counts, review objects, action types, safety labels, and ByteRange statuses;
- DSS certificate count and VRI signature match count;
- FieldMDP/DocMDP/UR3 method context and FieldMDP/UR3 category summaries;
- explicit false flags for permission-granted action execution, DSS-granted action execution, rights enforcement, signature validation, revocation checks, and trust-chain validation.

The focused fixture signs an initial revision containing DSS VRI material and permission transforms, then appends URI, Launch, and SubmitForm action objects outside the signed revision. Visible text remains only the page paragraph; signature bytes, certificate/OCSP payloads, digest bytes, and action operands stay out of visible WordPress text and JSON review output.

## Red-First Evidence

- Before the source change, `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPermissionDssActionChainCurrentBaseTest.php` failed with missing `permission_dss_action_chain_review` and `post_signature_action_count` fields.
- After implementation, the same command passed: `1 test files, 80 assertions, 0 failures`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPermissionDssActionChainCurrentBaseTest.php`
  - PASS: 1 file / 80 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityDssCertActionPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityDssActionByteRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfSecuritySignatureActionChainCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPermissionByteRangeFieldMdpCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPermissionDssActionChainCurrentBaseTest.php`
  - PASS: 5 files / 394 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-security-permission-dss-action-chain-currentbase.php`
  - PASS: emitted `post_signature_action_count=3`, methods `FieldMDP`, `DocMDP`, `UR3`, `dss_certificate_count=1`, `dss_vri_signature_match_count=1`, `post_signature_actions_granted_by_permissions=false`, `dss_validation_grants_action_execution=false`, and all execution flags false.

## Non-Overlap

This does not repeat accepted DSS action ByteRange review, inline annotation action-chain container review, FieldMDP ByteRange target coverage, DSS certificate action permission context, DSS signature reference-transform rows, or AcroForm DSS action attachment review. The new behavior is the combined permission + DSS + post-signature action-chain summary available in one review object for WordPress import decisions.

## Dependency Closure

No new support component is needed. This reuses native PHP PDF object parsing, AcroForm signature extraction, DSS stream hashing, action review extraction, and signature ByteRange object-span coverage. Upstream Python/model/PDFium execution remains outside this isolated slice.
