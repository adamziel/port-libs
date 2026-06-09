# Pandoc PDF Engine Handoff Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T000228Z`

Base accepted HEAD: `48a59c8d15f1cb4b103c2c2657a62cb105c4a87a`

## Summary

Added bounded native PHP PDF/X XMP identification handoff for fake-produced PDF
bytes. `PdfEngineHandoff::fakeRun()` now preserves:

- `pdfxid:GTS_PDFXVersion` as `pdfXmpMetadata['pdfxIdentification']['version']`;
- `pdfxid:GTS_PDFXConformance` as `pdfXmpMetadata['pdfxIdentification']['conformance']`;
- deterministic `pdf-byte-pdfx:<version>:<conformance>` diagnostics.

`PdfEngineHandoff::fakeRunSequence()` carries the same metadata through
`finalPdfXmpMetadata`. The WordPress PDF handoff smoke now exposes the PDF/X
identification in its review summary.

## Source Truth

This ports one bounded PDF-output review contract for metadata already present
in produced PDF bytes. It does not execute or implement Pandoc, TeX/PDF
engines, Typst, browser renderers, roff, external PDF validators, XML tools,
online services, live provider tests, or live-service provider tests.

The slice is distinct from accepted PDF handoff work for engine sidecars,
SyncTeX, recorder/transcript metadata, page boxes/labels/timings/viewports,
page display and production dictionaries, page content streams, marked content,
optional content, PDF/A and PDF/UA IDs, PDF/A extension schemas, output intents,
document info, URI base, named destinations, tagging, annotations, RichMedia,
forms, signatures, permissions, portfolios, threads, encryption preflight, and
external renderer parity.

## Verification

Baseline before implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1051 assertions, 0 failures`. The PDF/X XMP
identification case was absent.

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1057 assertions, 0 failures`.

```bash
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
```

Result: `pdf engine handoff self-test ok`.

Syntax, JSON, and whitespace verification:

```bash
php -l lanes/pandoc/src/PdfEngineHandoff.php
php -l lanes/pandoc/tests/PdfEngineHandoffTest.php
php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php
jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/pandoc
```

Result: all passed.

## Status Delta

- `phpPass`: `1996 -> 1997`.
- `benchmarkDenominator.mapped`: `2414 -> 2415`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 114`.
- Focused PDF test coverage: `1051 -> 1057` assertions.

## Dependency Closure

No new support component is needed. This reuses native `PdfEngineHandoff` XMP
metadata extraction, fake-produced PDF byte inspection, multipass fake-runner
summaries, and the existing WordPress PDF engine handoff example.

Upstream runner parity remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep PDF/X output-condition consistency between XMP and OutputIntents,
page-level print-production inheritance, encrypted-output decryption, real
renderer parity, PDF/A/PDF/UA/PDF/X validation, and external prepress checks as
separate bounded slices.
