# Pandoc PDF Engine Handoff Page Output Intents

Date: 2026-06-06 UTC
Base: `2fafdab3d147dccac973662b1b9ba5c7bdadcbfd`
Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T170041Z`

## Scope

This slice adds a bounded native PHP produced-PDF handoff for page-level
`/OutputIntents` arrays. `PdfEngineHandoff` now walks the produced PDF page
tree, extracts each page dictionary output intent, and exposes the summaries as
`pdfPageOutputIntents`. Multipass fake-runner plans carry the final run through
`finalPdfPageOutputIntents`.

The extraction reuses the existing PDF byte dictionary, indirect-object, string,
name, stream, and output-intent summary helpers. It preserves the page object,
source path, output condition identifier, registry, info, optional referenced ICC
profile summary, profile hash, and skipped-profile reason. The fake runner also
emits page-level diagnostics such as `pdf-byte-page-output-intents:N`.

## Non-Overlap

This does not repeat the accepted catalog-level `pdfOutputIntents` handoff. The
new behavior is specifically for `/OutputIntents` arrays attached to page
dictionaries in the page tree, which is useful when a produced PDF carries
page-specific proofing or prepress intent metadata instead of catalog-level
intent metadata.

No Pandoc binary, Cabal/Haskell runner, TeX/PDF engine, Typst engine, browser
renderer, roff engine, external PDF validator, online service, live provider
test, or live-service provider test was used.

## Evidence

- Baseline focused run before edits:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 638 assertions, 0 failures`.
- Red-first page-output-intent test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 641 assertions, 1 failures` because
  `pdfPageOutputIntents` was not present.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 645 assertions, 0 failures`.
- WordPress-relevant smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `PdfEngineHandoff.php`, `PdfEngineHandoffTest.php`, and
  `wordpress-pdf-engine-handoff.php`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1369 -> 1370`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1782 -> 1783`.
- `pdfEngineHandoffCoreCases`: `10 -> 11`.
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`.
- `pdfEngineHandoffCoreAssertions`: `95 -> 102`.

## Dependency Closure

No new support component is required. The slice reuses the bounded native PHP
PDF byte inspection helpers already present in `PdfEngineHandoff`.

Remaining out-of-scope work includes real PDF rendering, color-management
validation, ICC profile validation, PDF/X or PDF/A conformance checks, external
PDF validation tooling, TeX/Typst/browser/roff engines, and full upstream Pandoc
Haskell runner parity.

## Follow-Up

Next PDF-engine handoff work should stay renderer-independent and non-overlapping,
for example page-level prepress policy diagnostics, annotation or attachment
policy handoff, output-condition validation boundaries, or additional native PDF
metadata handoff needed by WordPress review flows.
