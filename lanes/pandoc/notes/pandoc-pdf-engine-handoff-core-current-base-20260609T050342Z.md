# Pandoc PDF Engine Handoff - DSS/VRI Consistency Policy

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T050342Z`
Base accepted HEAD: `61b62145a7f933d909276d2f3ea5cabaaa6f0b71`
Date: 2026-06-09 UTC

## Behavior

This slice adds a bounded native PHP policy summary for PDF Document Security
Store VRI evidence during fake PDF engine handoff inspection.

- `PdfEngineHandoff::fakeRun()` now exposes `pdfDocumentSecurityStorePolicy`.
- Sequence summaries now expose `finalPdfDocumentSecurityStorePolicy`.
- The policy checks VRI names against known signature `Contents` SHA-256
  digests and checks VRI Cert/OCSP/CRL references against top-level DSS arrays.
- Diagnostics now record DSS policy status, VRI counts, matched VRI counts,
  per-status VRI totals, and grouped issue counts.

The new focused fixture covers one VRI whose name matches a signature digest
and one unmatched VRI with missing Cert/CRL references. The WordPress-relevant
example smoke includes the new policy handoff fields and review diagnostics
without invoking TeX, Typst, browsers, Pandoc, LibreOffice, zip/unzip, or any
external service.

## Non-Overlap

This does not repeat raw DSS dictionary extraction, signature byte-range
handoff, visual signature appearance handling, FieldMDP policy checks,
signature lock/seed checks, name tree/page label/parent tree/ID tree policies,
associated-file summaries, or conformance summaries. It adds the next bounded
DSS/VRI consistency layer on top of the existing PDF object and signature
inspection helpers.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF
object parser, signature extraction, and DSS extraction helpers in
`PdfEngineHandoff`. Full cryptographic signature verification, certificate
chain validation, OCSP/CRL validation, timestamp validation, PDF rendering, and
upstream engine parity remain outside this no-engine fake-runner slice.

## Delta

- Focused PHP PASS cases: `2345 -> 2346` (`+1`).
- Focused assertions in `PdfEngineHandoffTest.php`: `1255 -> 1267` (`+12`).
- Manifest mapped denominator: `2740 -> 2741` (`+1`).
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 120`.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1267 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`.
- `git diff --check -- lanes/pandoc`

Root harness: not run - isolated micro-slice.

## Next Task

The next PDF engine handoff slice should remain no-engine and can extend the
policy layer to bounded DocMDP permission summaries, timestamp token handoff
metadata, or richer DSS evidence typing. It should still avoid crypto/cert
validation and external renderer or PDF engine execution.
