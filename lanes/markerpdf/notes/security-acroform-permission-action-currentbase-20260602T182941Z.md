# markerPDF AcroForm permission action current-base review

Slice: `security-permission-action-currentbase`

Base accepted HEAD: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

- Upstream marker/markerPDF at the manifest-pinned source converts PDFs into Markdown/JSON/HTML document structures and form blocks through the PDF extraction/model pipeline; it is a content import path, not a PDF viewer action executor.
- Upstream reference: https://github.com/datalab-to/marker/blob/master/README.md documents marker as a PDF/document conversion pipeline with forms as extracted blocks.
- PDF action reference: https://opensource.adobe.com/dc-acrobat-sdk-docs/library/pdfmark/pdfmark_Actions.html lists JavaScript, Launch, SubmitForm, ResetForm, ImportData, Hide, URI, GoTo, and GoToR as PDF action types. These are viewer/action semantics, so WordPress import must surface them as review metadata only.
- PDF signature permission semantics keep DocMDP/field-lock declarations as permission metadata. This slice still does not validate signatures, enforce permissions, submit form data, import FDF data, launch files, run JavaScript, or execute external PDF tooling.

## Implemented Behavior

- `PdfSecurityPreflight` now bridges AcroForm field actions into `document_action_security_review`, including SubmitForm, ImportData, Hide, and JavaScript field-level rows.
- Existing widget annotation action rows are enriched with AcroForm field context instead of duplicated when a widget is already page-referenced as an annotation.
- The review now reports AcroForm action counts, submit/reset/import/hide/JavaScript counts, signed-locked field action counts, locked-by-signature names, DocMDP permission labels, form action field names, and blocked `form_action_execution`.
- Action payloads remain review-only: JavaScript payload previews and signature contents bytes are not copied into the security JSON or visible WordPress text.

## Non-Overlap

This does not repeat accepted encrypted permission-handler review, public-key recipient permission envelopes, Standard authentication digest review, signature ByteRange/DSS/DocMDP correlation, Launch/URI catalog and annotation security review, standalone AcroForm action extraction, widget appearance/action review, OpenAction next-chain review, page transition actions, or rich-media action boundaries. The new behavior is the security-preflight correlation of AcroForm field/widget action rows with DocMDP signed field-lock permission context.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `PdfSecurityPreflight`, `PdfAcroFormExtractor`, `PdfAnnotationExtractor`, `PdfOutlineExtractor`, and `PdfTextExtractor`. Full CMS/PKCS#7 signature validation, X.509 trust-chain processing, revocation checks, JavaScript execution, FDF/XFDF import/export, permission enforcement, pypdfium/pdftext execution, Python models, and external PDF tools remain out of scope.

## Verification

- Red-first focused command before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php` failed with `1 test files, 16 assertions, 1 failures`; the missing behavior was `acroform_actions_present` and related document-action review metadata.
- Focused passing command: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php` passed `1 test files, 76 assertions, 0 failures` with 2 PASS lines.
- Focused regression command: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfAcroFormSignatureXfaWidgetActionReviewCurrentBaseTest.php` passed `5 test files, 1380 assertions, 0 failures` with 40 PASS lines.
- WordPress smoke command: `php lanes/markerpdf/examples/wordpress-pdf-security-acroform-permission-action-currentbase.php` emitted `plain_text_imported=true`, `import_decision=review_required_signature_metadata`, `action_count=7`, `acroform_action_count=4`, `signed_locked_field_action_count=7`, `form_submit_action_count=1`, `unsafe_uri_action_count=1`, `raw_review_material_exposed=false`, and execution flags false.
- PHP lint passed for `lanes/markerpdf/src/PdfSecurityPreflight.php`, `lanes/markerpdf/tests/PdfSecurityAcroFormPermissionActionCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-security-acroform-permission-action-currentbase.php`.
- `git diff --check -- lanes/markerpdf` passed.
