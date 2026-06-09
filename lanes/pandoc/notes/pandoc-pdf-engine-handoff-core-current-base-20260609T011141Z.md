# Pandoc PDF Engine Handoff Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T011141Z`

Base accepted HEAD: `09109401d59cee7a589aaf8125432abbe4aef718`

## Summary

Added bounded native PHP PDF/A and PDF/UA conformance review-policy handoff for
fake-produced PDF bytes. `PdfEngineHandoff::fakeRun()` now exposes
`pdfConformancePolicy` when produced PDF XMP claims PDF/A or PDF/UA identity.
The policy summarizes:

- claimed PDF/A part and conformance;
- claimed PDF/UA part, amendment, and corrigendum;
- encryption state;
- catalog language;
- tagging and StructTreeRoot presence;
- document-level OutputIntent count;
- review issues for encrypted PDF/A output, missing PDF/A output intents,
  missing PDF/UA MarkInfo, missing PDF/UA structure tree, and missing PDF/UA
  catalog language.

`PdfEngineHandoff::fakeRunSequence()` carries the same summary through
`finalPdfConformancePolicy`. The WordPress PDF handoff smoke now exposes the
single-run and final-run policy summaries for reviewer queues.

## Source Truth

This ports one bounded produced-byte review contract using metadata already
available to the fake runner: XMP identifiers, OutputIntent dictionaries,
catalog `/Lang`, MarkInfo, StructTreeRoot, and encryption preflight. It does
not validate PDF/A or PDF/UA conformance, decrypt encrypted PDFs, render PDFs,
or execute Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external
PDF validators, JavaScript, online services, live provider tests, or
live-service provider tests.

The slice is distinct from accepted PDF handoff work for engine sidecars,
SyncTeX, recorder/transcript metadata, page boxes/labels/timings/viewports,
page display and production dictionaries, URI base, named destinations, XMP
metadata extraction, PDF/X output-intent policy, tagging extraction,
annotations, RichMedia, forms, signatures, permissions, portfolios, threads,
encryption preflight, and external renderer parity.

## Verification

Current accepted PDF-engine focused baseline from the prior PDF-engine note:
`1 test files, 1072 assertions, 0 failures`.

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: `1 test files, 1086 assertions, 0 failures`.

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

- `phpPass`: `2027 -> 2028`.
- `benchmarkDenominator.mapped`: `2442 -> 2443`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 122`.
- Focused PDF test coverage: `1072 -> 1086` assertions.

## Dependency Closure

No new support component is needed. This reuses native `PdfEngineHandoff` XMP
metadata extraction, OutputIntent parsing, catalog language extraction, tagging
metadata extraction, encryption preflight, produced-byte inspection, multipass
fake-runner summaries, and the existing WordPress PDF engine handoff example.

Upstream runner parity remains gated on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep PDF/A output-condition registry normalization, PDF/A/PDF/UA external
validation, encrypted-output decryption, real renderer parity, page-level
print-production inheritance, page separation metadata, and external prepress
checks as separate bounded slices.
