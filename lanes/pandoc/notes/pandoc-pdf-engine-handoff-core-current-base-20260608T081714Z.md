# Pandoc PDF Engine Handoff Current-Base LegalAttestation Slice

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T081714Z`
Accepted base: `9318ce97f670bb0c379833b2a4213a7bf03ac886`

## Status Delta

- Added native produced-PDF catalog `/LegalAttestation` handoff support in `PdfEngineHandoff`.
- The fake runner now reports `pdfLegalAttestationMetadata` and `finalPdfLegalAttestationMetadata` with catalog object reference, type, language, status, jurisdiction, associated-file references, bounded attestation stream byte count, SHA-256 hash, and skipped-stream reason when filtered or too large.
- Added diagnostics: `pdf-byte-legal-attestation`, `pdf-byte-legal-attestation-status:*`, `pdf-byte-legal-attestation-jurisdiction:*`, `pdf-byte-legal-attestation-stream-bytes:*`, `pdf-byte-legal-attestation-text`, `pdf-byte-legal-attestation-skipped:*`, and `pdf-byte-legal-attestation-associated-files:*`.
- Updated the WordPress PDF engine handoff example so the synthetic review packet exposes LegalAttestation metadata in both single-run and final sequence summaries.
- Mapped denominator moved to `2000`; lane `phpPass` moved to `1579`.

## Focused Evidence

- Rework check: no `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 814 assertions, 1 failures`
  - Expected failure: `pdfLegalAttestationMetadata` was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 820 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`
- PHP lint:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
  - `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- JSON validation: `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local PDF byte inspection helpers for catalog dictionary parsing, indirect object resolution, reference arrays, and bounded stream hashing. No Pandoc, TeX/PDF engine, Typst, browser renderer, roff renderer, external PDF validator, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This intentionally avoids already accepted PDF-engine clusters for XMP/PDF-A/PDF-UA metadata, output intents, tagged structure, page display metadata, catalog URI base, catalog requirements, collection portfolios, acroforms, signatures, active actions, RichMedia annotations, optional content, embedded files, page metadata, PieceInfo, and WebCapture/SpiderInfo. The owned behavior is the catalog `/LegalAttestation` review metadata handoff only.

## Next Task

For follow-up, pick a non-overlapping produced-PDF handoff gap such as PDF 2.0 catalog review metadata, viewer/page lifecycle metadata, or bounded validation diagnostics while keeping the no-external-engine/no-validator boundary.
