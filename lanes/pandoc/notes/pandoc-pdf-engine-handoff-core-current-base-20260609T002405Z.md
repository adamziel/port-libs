# Pandoc PDF Engine Handoff Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T002405Z`

Base accepted HEAD: `72cabc3f4f492b184408152fdc147cadc8cc603f`

## Summary

Added bounded native PHP PDF/X output-intent consistency handoff for
fake-produced PDF bytes. `PdfEngineHandoff::fakeRun()` now exposes:

- `pdfOutputIntentPolicy['reviewStatus']` as `ok` or `review`;
- PDF/X XMP version and conformance from the produced PDF metadata packet;
- document-level and page-level PDF/X OutputIntent counts;
- embedded PDF/X destination profile counts;
- review issues for page-scoped PDF/X intents, missing PDF/X intents, missing
  PDF/X XMP identification, missing output-condition identifiers, and missing
  destination profiles;
- deterministic `pdf-byte-output-intent-policy:*` diagnostics.

`PdfEngineHandoff::fakeRunSequence()` carries the same policy through
`finalPdfOutputIntentPolicy`. The WordPress PDF handoff smoke now exposes the
single-run and final-run policy summaries for reviewer queues.

## Source Truth

This ports one bounded PDF-output review contract for metadata already present
in produced PDF bytes. It does not execute or implement Pandoc, TeX/PDF
engines, Typst, browser renderers, roff, external PDF validators, XML tools,
online services, live provider tests, or live-service provider tests.

The slice is distinct from accepted PDF handoff work for engine sidecars,
SyncTeX, recorder/transcript metadata, page boxes/labels/timings/viewports,
page display and production dictionaries, page content streams, marked content,
optional content, XMP/PDF-A/PDF-UUID extraction, base OutputIntent extraction,
page OutputIntent extraction, document info, URI base, named destinations,
tagging, annotations, RichMedia, forms, signatures, permissions, portfolios,
threads, encryption preflight, and external renderer parity.

## Verification

Baseline before implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1057 assertions, 0 failures`. The PDF/X
output-intent policy consistency case was absent.

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1072 assertions, 0 failures`.

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

- `phpPass`: `2009 -> 2010`.
- `benchmarkDenominator.mapped`: `2425 -> 2426`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 123`.
- Focused PDF test coverage: `1057 -> 1072` assertions.

## Dependency Closure

No new support component is needed. This reuses native `PdfEngineHandoff` XMP
metadata extraction, OutputIntent dictionary parsing, page OutputIntent parsing,
fake-produced PDF byte inspection, multipass fake-runner summaries, and the
existing WordPress PDF engine handoff example.

Upstream runner parity remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep page-level print-production inheritance, PDF/X output-condition registry
normalization, encrypted-output decryption, real renderer parity,
PDF/A/PDF/UA/PDF/X validation, external prepress checks, and page separation
metadata as separate bounded slices.
