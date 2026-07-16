# PDF XFA packet provenance slice

Bead: `plib-fw574`
Base: `8ff9a9598a` after rebase from queued base
`125d4120dca0bda161ee22847e93381b2f45a134`
Date: 2026-06-17 UTC

## Scope

Added bounded produced-PDF AcroForm `/XFA` packet provenance to `PdfEngineHandoff`.
Fake-run inspection now exposes `pdfXfaPackets` with packet name, packet object,
source label, value kind, filters, bounded packet byte count, sha256 when
unfiltered and in budget, and skipped reason for filtered or oversized packets.
Sequence results propagate the final packet inventory through `finalPdfXfaPackets`.

This is limited to native produced-byte PDF inspection in `lanes/pandoc`. It does
not invoke Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
external validators, online services, or live-service provider tests.

## Coverage

Added one focused `PdfEngineHandoffTest` fixture covering AcroForm `/XFA` named
packets backed by unfiltered and filtered stream objects. The fixture asserts
fake-run payload, sequence payload, byte/hash handling, filter skip handling,
and deterministic diagnostics.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2758 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 test files, 175147 assertions, 0 failures
