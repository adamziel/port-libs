# markerPDF Launch/URI Certificate Permission Security Review

Slice: `security-launch-uri-cert-permission-currentbase-20260602T175042Z`

Base accepted HEAD: `24de00fbf08b8b36bc385e6e3414b4e0638c09d6`

## Source Truth

Upstream marker/markerPDF at the manifest-pinned source uses a PDF provider backed by pdftext/pypdfium page text, links, page references, and rendered page images, then runs document processors over extracted page blocks. That import boundary is content extraction and review metadata; it does not execute PDF viewer actions, Launch actions, JavaScript, signing, signature validation, revocation checks, trust-chain validation, Python action payloads, or external PDF tools during WordPress import.

Relevant PDF parser behavior for this slice: catalog `/OpenAction`, annotation `/A`, annotation `/AA`, and `/Next` action chains can contain `/S /URI` and `/S /Launch` dictionaries. PDF viewers may open URI targets or launch external applications, so the native port must surface those as sanitized security-review rows only. Catalog `/Perms /DocMDP` points at a certifying signature whose `/Reference` transform permission level controls allowed document changes, but this lane still only summarizes the declared permission metadata and never validates the signature or enforces rights.

## Implemented Behavior

- `PdfSecurityPreflight` now includes `document_action_security_review` with catalog OpenAction rows, page additional-action rows, page annotation action rows, annotation additional-action rows, action type/safety counts, unsafe URI counts, Launch counts, and certifying-signature DocMDP permission labels.
- Unsafe document actions add `unsafe_pdf_actions_present`, `launch_actions_present`, and `unsafe_uri_actions_present` review reasons, plus a `pdf_action_execution` blocked operation.
- The report keeps `executes_pdf_actions`, `executes_signature_validation`, `executes_revocation_check`, `executes_trust_chain_validation`, `executes_python_or_models`, and `executes_external_pdf_tools` false.
- The focused fixture keeps Launch/URI targets and raw signature contents out of visible WordPress text.

## Non-Overlap

This does not repeat accepted encrypted permission-handler review, public-key recipient permission envelopes, standard authentication digest review, signature ByteRange/DSS/DocMDP correlation, JavaScript action-chain review, OpenAction-only safety review, page transition/action metadata, link annotation destination promotion, or rich-media action target boundaries. The new behavior is the security preflight correlation of Launch/URI action rows with certifying-signature permission context.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `PdfOutlineExtractor`, `PdfAnnotationExtractor`, `PdfActionReviewExtractor`, `PdfAcroFormExtractor`, and `PdfTextExtractor`; no Python, pypdfium, pdftext, model, JavaScript, signature, revocation, trust-chain, shell, or external PDF-tool execution is introduced.

## Verification

- Red-first focused test before implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php` failed with missing `document_action_security_review` and missing action review reasons; the visible-text isolation case passed.
- Focused passing command after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php` passed `1 test files, 44 assertions, 0 failures` with 2 PASS lines.
- Focused security regression command: `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php` passed `2 test files, 452 assertions, 0 failures` with 14 PASS lines.
- PHP lint passed for `lanes/markerpdf/src/PdfSecurityPreflight.php`, `lanes/markerpdf/tests/PdfSecurityLaunchUriCertPermissionCurrentBaseTest.php`, and `lanes/markerpdf/examples/wordpress-pdf-security-launch-uri-cert-permission-currentbase.php`.
- Lane JSON validation passed for `lanes/markerpdf/lane-status.json` and `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
- WordPress smoke example command: `php lanes/markerpdf/examples/wordpress-pdf-security-launch-uri-cert-permission-currentbase.php` emitted `plain_text_imported: true`, `action_count: 6`, `launch_action_count: 2`, `unsafe_uri_action_count: 2`, `doc_mdp_permission_label: form_fill_templates_signatures_annotations`, `raw_signature_material_exposed: false`, and execution flags false.
- Whitespace check: `git diff --check -- lanes/markerpdf` passed.
