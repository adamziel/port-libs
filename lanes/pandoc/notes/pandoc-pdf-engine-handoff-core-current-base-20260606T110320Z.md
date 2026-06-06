# Pandoc PDF Engine Handoff Current Base

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T110320Z`

Base accepted HEAD: `7344a4e71f586163a7f26e45c5c3d1a246701f1a`

## Summary

Added bounded native PHP produced-PDF optional-content membership handoff for
page-resource `/Properties` entries whose dictionaries declare `/Type /OCMD`.
`PdfEngineHandoff::fakeRun()` now emits `pdfOptionalContentMemberships` with:

- page number and page object reference;
- resource property name and OCMD object reference or inline marker;
- inherited page-resource provenance;
- `/OCGs` group references;
- `/P` visibility policy;
- `/VE` visibility-expression operators and group references.

`PdfEngineHandoff::fakeRunSequence()` carries the same data as
`finalPdfOptionalContentMemberships`. OCMD property dictionaries are now kept
out of the generic `pdfMarkedContentProperties` summary so existing marked
content review metadata remains stable.

The WordPress PDF handoff smoke now exposes the OCMD membership record for the
fake layered chart resource.

## Source Truth

This ports the bounded PDF-output handoff contract for fake-produced PDF bytes.
It does not execute or implement Pandoc, TeX/PDF engines, Typst, browser
renderers, roff, JavaScript, external PDF validators, online services, or live
provider tests.

The slice is distinct from accepted PDF handoff work for engine sidecars,
SyncTeX, recorder/transcript metadata, page boxes/labels/timings/viewports,
page display metadata, page content stream operators, marked-content associated
files, optional content groups/default config, XMP/PDF-A, output intents,
document info, URI base, named destinations, tagging, annotations, RichMedia,
forms, signatures, permissions, portfolios, threads, encryption preflight, and
external renderer parity.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: failed with `1 test files, 624 assertions, 1 failures` because
`pdfOptionalContentMemberships` was absent.

After implementation:

```bash
php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php
```

Result: passed with `1 test files, 630 assertions, 0 failures`.

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

- `phpPass`: `1306 -> 1307`.
- `benchmarkDenominator.mapped`: `1720 -> 1721`.
- `pdfEngineHandoffCoreCases`: `10 -> 11`.
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`.
- `pdfEngineHandoffCoreAssertions`: `95 -> 103`.
- Focused PDF test coverage: `622 -> 630` assertions compared with the latest
  accepted PDF handoff note; red-first with the new expectation reported 624
  assertions and one failure.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`PdfEngineHandoff` PDF dictionary/page-resource helpers and reuses the focused
PHP test harness plus the WordPress PDF handoff example.

Upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, Cabal project/package files, and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep OCMD visibility-expression evaluation, content-stream marked-content
operator correlation, layer-state simulation, encrypted-output decryption,
PDF/A/UA validation, and real renderer parity as separate bounded slices.
