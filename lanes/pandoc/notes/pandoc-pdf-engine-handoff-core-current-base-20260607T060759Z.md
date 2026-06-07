# Pandoc PDF Engine Handoff Current Base

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260607T060759Z`
- Accepted base: `45539ec04b8219d154701e97e362a3479d34ee84`
- Date: 2026-06-07 UTC

## Scope

This slice adds bounded native PHP produced-PDF XMP handoff metadata for PDF/UA
identification. It does not validate PDF/UA conformance and does not invoke
Pandoc, Cabal, Haskell runners, TeX/PDF engines, Typst, browser renderers,
roff, external PDF validators, JavaScript, online services, live provider
tests, or live-service provider tests.

## Behavior

`PdfEngineHandoff::fakeRun()` now resolves XMP identification elements by their
declared namespace URI instead of assuming fixed prefixes. PDF/A
`pdfaid:part`/`pdfaid:conformance` remains separate from PDF/UA
`pdfuaid:part`/`pdfuaid:amd`/`pdfuaid:corr`, even when the PDF/UA namespace is
bound to a different prefix in the XMP packet.

The fake-runner summary now exposes:

- `pdfXmpMetadata.pdfuaIdentification.part`
- `pdfXmpMetadata.pdfuaIdentification.amendment`
- `pdfXmpMetadata.pdfuaIdentification.corrigendum`
- `pdf-byte-pdfua:*` diagnostics for WordPress review packets

`fakeRunSequence()` carries the same data through `finalPdfXmpMetadata`.

## Evidence

- Baseline focused command:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 707 assertions, 0 failures`
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 709 assertions, 0 failures`
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  -> `pdf engine handoff self-test ok`

## Final Required Checks

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  -> `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
  -> `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `git diff --check -- lanes/pandoc`
  -> no output

## Counter Delta

- `lanes/pandoc/lane-status.json` `phpPass`: unchanged at `1459`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1876 -> 1877`
- PDF engine handoff cases: `11 -> 12`
- Mapped PDF engine handoff cases: `11 -> 12`
- PDF engine focused assertions: `106 -> 108`
- Focused `PdfEngineHandoffTest.php` assertions: `707 -> 709`

## Dependency Closure

No new support component is required. The slice reuses native PHP
`PdfEngineHandoff` PDF byte inspection, bounded XMP parsing helpers, the
existing fake-runner file-map/result contract, and the WordPress PDF handoff
example.

Full PDF/A/PDF/UA conformance validation, structure-tree semantic validation,
real renderer execution, external validator parity, compressed XMP stream
decoding, and upstream Haskell runner parity remain separate bounded work.

## Non-Overlap

This slice does not repeat accepted PDF sidecar/log, SyncTeX, recorder,
transcript, trailer/xref/object-stream, page tree/boxes/display/timings/
viewports/content-stream, font/image/form-XObject/graphics-state, page-label,
document-info, PDF/A extraction, output-intent, URI base, viewer-preference,
tagging/structure, annotation, RichMedia, embedded-file, optional-content,
AcroForm, signature, catalog permission, active-action, collection, thread,
linearization, color-space, or encryption surfaces. It is limited to
namespace-qualified PDF/UA XMP identification handoff.

Root harness not run - isolated micro-slice.
