# Pandoc PDF Engine Handoff Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T113639Z`

Base accepted HEAD: `a238e36374993d9d8ee1e4b4fb86ace86acaec5e`

## Summary

Added bounded native PHP produced-PDF page production metadata handoff for page
dictionary `/BoxColorInfo`, `/SeparationInfo`, and `/PresSteps` entries.
`PdfEngineHandoff::fakeRun()` now emits `pdfPageProductionMetadata` with:

- page number and page object reference;
- inline or indirect box-color info provenance;
- per-box color arrays, rule widths, and rule styles;
- separation info object references, page references, colorant names, and color
  spaces;
- inline or indirect presentation-step provenance, subtype, and next-step
  references.

`PdfEngineHandoff::fakeRunSequence()` carries the same data as
`finalPdfPageProductionMetadata`. The WordPress PDF handoff smoke now exposes
the new metadata and diagnostics for the fake-produced PDF review packet.

## Source Truth

This ports the bounded PDF-output handoff contract for fake-produced PDF bytes.
It does not execute or implement Pandoc, TeX/PDF engines, Typst, browser
renderers, roff, JavaScript, external PDF validators, online services, live
provider tests, or live-service provider tests.

The slice is distinct from accepted PDF handoff work for engine sidecars,
SyncTeX, recorder/transcript metadata, page boxes/labels/timings/viewports,
page display metadata, page content stream operators, marked-content associated
files, optional content groups/default config/memberships, XMP/PDF-A, output
intents, document info, URI base, named destinations, tagging, annotations,
RichMedia, forms, signatures, permissions, portfolios, threads, encryption
preflight, and external renderer parity.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: failed with `1 test files, 632 assertions, 1 failures` because
`pdfPageProductionMetadata` was absent.

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: passed with `1 test files, 638 assertions, 0 failures`.

```bash
php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test
```

Result: `pdf engine handoff self-test ok`.

Syntax and whitespace verification were also run before handoff:

```bash
php -l lanes/pandoc/src/PdfEngineHandoff.php
php -l lanes/pandoc/tests/PdfEngineHandoffTest.php
php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php
git diff --check -- lanes/pandoc
```

## Status Delta

- `phpPass`: `1317 -> 1318`.
- `benchmarkDenominator.mapped`: `1731 -> 1732`.
- `pdfEngineHandoffCoreCases`: `10 -> 11`.
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`.
- `pdfEngineHandoffCoreAssertions`: `95 -> 103`.
- Focused PDF test coverage: `630 -> 638` assertions.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`PdfEngineHandoff` PDF dictionary/page-tree helpers and reuses the focused PHP
test harness plus the WordPress PDF handoff example.

Upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep PDF/X output-condition validation, separation colorant simulation,
page-production step execution semantics, inherited production metadata beyond
direct page dictionaries, encrypted-output decryption, real renderer parity,
PDF/A/PDF/UA validation, and external prepress checks as separate bounded
slices.
