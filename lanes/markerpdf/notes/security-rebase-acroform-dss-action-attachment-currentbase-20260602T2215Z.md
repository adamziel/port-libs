# Security Rebase AcroForm DSS Action Attachment Current Base

Session: `port-dev-markerpdf-security73-20260602T221545Z`
Base: `e125b1864e3d759fe9909dd2c4d72359f1c4fbdb`

## Source Truth

- Upstream markerPDF imports through `pdftext`/PDFium-style extraction boundaries: PDF actions, validation material, embedded files, and model/runtime helpers are not executed as visible document text.
- PDF source truth for this slice: AcroForm `SubmitForm`, `ImportData`, and `Launch` actions can target FileSpec dictionaries with embedded-file streams; PDF `/DSS` and signature `/Reference` transforms are validation and permission metadata, not executable import permissions.
- Existing native source reused: `PdfAcroFormExtractor` already parses action FileSpec review metadata with decoded stream hashes and payload omission flags, and `PdfDocumentSecurityStoreExtractor` already parses DSS/VRI validation stream summaries.

## Implemented

- Rebased the previously queued security/acform/DSS/action/attachment bundle onto current accepted markerPDF base after the `PdfSecurityPreflight` overlap.
- `PdfSecurityPreflight` now preserves AcroForm action target/platform FileSpec review rows in `document_action_security_review`.
- Added aggregate `action_file_spec_security_review` reporting FileSpec objects, filenames, relationships, embedded-file counts/objects/hashes, related-file hashes, source action types, and review-only flags.
- The DSS/certificate/action/permission bundle summary now includes action FileSpec and embedded-file counts so WordPress importers can review signed form action attachments from one security surface.
- Raw signature contents, certificate/OCSP bytes, embedded-file payloads, action targets, and catalog associated-file payloads remain excluded from visible WordPress text and security JSON payload material.

## Verification

- `php -l lanes/markerpdf/src/PdfSecurityPreflight.php`
- `php -l lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-security-acroform-dss-action-attachment-bundle-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityAcroFormDssActionAttachmentBundleCurrentBaseTest.php`
  - `1 test files, 100 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfSecurity*Test.php`
  - `14 test files, 1393 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-security-acroform-dss-action-attachment-bundle-currentbase.php`
  - smoke passed and emitted WordPress-safe review output
- `git diff --check -- lanes/markerpdf`

## Delta

- Behavior tests: `899 -> 901 pass / 0 fail` in lane-focused evidence.
- Mapped semantics: `634 -> 635 / 78`.
- New WordPress scenario: signed AcroForm action attachment review with DSS/VRI certificate hashes and FieldMDP/UR3 permission context.

## Non-Overlap

Avoided repeating accepted DSS byte-range action review, DSS certificate permission/open-action review, AcroForm action FileSpec extraction, catalog/page associated-file extraction, runtime metadata, page annotation, and outline target security review. This slice only composes existing AcroForm/DSS rows at the security preflight layer and adds action FileSpec aggregate review metadata.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF object parsing, AcroForm action FileSpec review, DSS extraction, embedded-file extraction, and text extraction. No Python models, PDFium, pypdfium2, pdftext runtime, signature validation, revocation checking, decryption, action execution, or external PDF tools are required.
