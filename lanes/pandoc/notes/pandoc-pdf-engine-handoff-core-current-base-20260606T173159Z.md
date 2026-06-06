# Pandoc PDF Engine Handoff Annotation Appearances

Date: 2026-06-06 UTC
Base: `f0133633366ca90b1289c4c40a7a9202c36d9be1`
Slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T173159Z`

## Scope

This slice adds bounded native PHP produced-PDF handoff support for annotation
appearance dictionaries. `PdfEngineHandoff` now walks page annotations, resolves
`/AP` normal, rollover, and down appearance entries, expands state
subdictionaries, and summarizes referenced Form appearance streams.

The fake runner exposes the result as `pdfAnnotationAppearances` and carries the
final multipass result through `finalPdfAnnotationAppearances`. Each summary
records page and annotation provenance, subtype, field name, selected `/AS`
state, appearance/state names, appearance object reference, BBox, Matrix,
resource presence, transparency group metadata, filters, stream byte counts,
stream SHA-256 hashes, and bounded stream skip reasons.

## Non-Overlap

This does not repeat accepted annotation detail, RichMedia, Form XObject, active
action, or page-output-intent handoffs. The new behavior is specifically about
annotation-local `/AP` appearance dictionaries and their state/Form stream
handoff, which is needed for renderer-independent review of produced PDF form
widget appearances.

No Pandoc binary, Cabal/Haskell runner, TeX/PDF engine, Typst engine, browser
renderer, roff engine, external PDF validator, JavaScript execution, online
service, live provider test, or live-service provider test was used.

## Evidence

- Baseline focused run before edits:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 645 assertions, 0 failures`.
- Red-first annotation-appearance test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed with `1 test files, 647 assertions, 1 failures` because
  `pdfAnnotationAppearances` was absent.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 654 assertions, 0 failures`.
- WordPress-relevant smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed with `pdf engine handoff self-test ok`.
- PHP lint passed for `PdfEngineHandoff.php`, `PdfEngineHandoffTest.php`, and
  `wordpress-pdf-engine-handoff.php`.
- Diff hygiene:
  `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1376 -> 1377`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1789 -> 1790`.
- `pdfEngineHandoffCoreCases`: `10 -> 11`.
- `mappedPdfEngineHandoffCoreCases`: `10 -> 11`.
- `pdfEngineHandoffCoreAssertions`: `95 -> 104`.

## Dependency Closure

No new support component is required. The slice reuses the bounded native PHP
PDF byte inspection helpers already present in `PdfEngineHandoff`, including
object lookup, page-tree walking, dictionary/array parsing, stream extraction,
filter summaries, color-space summaries, fake-runner diagnostics, and multipass
sequence summaries.

Remaining out-of-scope work includes rendering annotation appearances, executing
annotation actions or JavaScript, PDF conformance validation, external PDF
validation tooling, TeX/Typst/browser/roff engines, and full upstream Pandoc
Haskell runner parity.

## Follow-Up

Next PDF-engine handoff work should stay renderer-independent and
non-overlapping, for example page-level prepress policy diagnostics, additional
form/annotation policy metadata, attachment policy handoff, or output-condition
validation boundaries.
