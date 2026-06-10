# Pandoc PDF Page Viewport Policy Current-Base Slice

Slice: `plib-e27c`, PDF/Typst boundary provenance.

## Behavior

`PdfEngineHandoff` now summarizes already-extracted PDF page viewport metadata
into `pdfPageViewportPolicy` and carries the same metadata through
`finalPdfPageViewportPolicy` in fake-run sequences.

The policy records page and viewport counts, measured and unmeasured viewport
counts, pages with measurement overlays, missing bounding boxes, missing measure
metadata, missing scale ratios, missing unit formats, measure subtype counts,
unit-format counts, and sorted review issues.

This is inert reviewer metadata over fake-produced PDF bytes. It does not run
Pandoc, Typst, TeX/PDF engines, browser renderers, external PDF validators,
online services, or live provider tests.

## Evidence

- Red-first focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1477 assertions, 1 failures`
  - Failure: `pdfPageViewportPolicy` was absent.
- Syntax checks:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Focused verification:
  - `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1488 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`
- Full Pandoc PHP gate:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 60465 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `2983 -> 2984`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3143 -> 3144`.
- PDF engine handoff core mapped cases: `14 -> 15`.
- PDF engine handoff core assertions: `128 -> 141`.

## Non-Overlap

This slice does not repeat existing PDF viewport extraction, page timing/action
policies, viewer preference policy, name-tree policy, structure-tree policy,
annotation appearance policy, stream filter policy, collection policy,
JavaScript/action policy, signature policy, or Typst dependency sidecar
provenance. It adds only a bounded policy layer over page viewport measurement
metadata already parsed by native PHP fake-run inspection.
