# ODF attachment office documents

Slice: `plib-8r74r` (`2026-06-28`).

## Scope

- `Attachments/` package sidecars now infer common office document media types
  for ODF (`.odt`, `.ods`, `.odp`, `.odg`, `.odf`, `.odc`) and OpenXML
  (`.docx`, `.xlsx`, `.pptx`) package members in both `OdfReader` and
  `OpenDocumentPackage`.
- Declared and undeclared office-document attachments are classified as
  `attachment-document-resource` instead of falling through to a generic
  attachment resource or missing-media-type issue.
- Attachment payload bytes remain metadata-only under
  `attachment-package-bytes-blocked` and stay out of document media/WordPress
  handoff.

## Direct-Format Accounting

- Extended the ODF attachment package sidecar fixture from 7 to 8 attachment
  items.
- Focused ODF attachment/OpenDocument package gate currently reports 2,241
  assertions after rebasing over the latest integration lane.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 2 test files, 2,241 assertions, 0 failures

No Pandoc, office suites, TeX/browser engines, unzip/zip commands, Jupyter,
Node tooling, external validators, or online services were invoked.
