# Pandoc PDF Engine Handoff Current Base

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T071610Z`
- Accepted base: `e5e7af20fff34a2939cfb21b04f9bc546415b4cf`
- Date: 2026-06-06 UTC

## Scope

This slice adds bounded native PHP handoff diagnostics for fake-produced PDF
page `/VP` viewport dictionaries and `/Measure` unit-format metadata. It does
not implement or invoke TeX, Typst, browser, roff, external PDF validators,
Pandoc, Cabal, Haskell runners, JavaScript, online sanitizers, online services,
or live provider tests.

## Behavior

`PdfEngineHandoff::fakeRun()` now includes `pdfPageViewports` when produced PDF
bytes contain page viewport metadata. `PdfEngineHandoff::fakeRunSequence()`
carries the final-pass equivalent as `finalPdfPageViewports`.

For referenced and inline viewport dictionaries, the summary records:

- page and viewport object references;
- source path such as `page:3 0 R.VP[0]`;
- viewport `Name`, decoded from literal or UTF-16 hex strings;
- viewport `BBox`;
- `/Measure` subtype and scale ratio;
- bounded X, Y, distance, area, and angle number-format unit metadata.

## Evidence

- Baseline before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 601 assertions, 0 failures`
- Red-first after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 603 assertions, 1 failures`
  - The failure was the missing `pdfPageViewports` result.
- Final focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 608 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  -> `pdf engine handoff self-test ok`

## Final Required Checks

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  -> `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
  -> `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  -> `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  -> no output

## Counter Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1238 -> 1239`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1681 -> 1682`
- PDF engine handoff cases: `10 -> 11`
- Mapped PDF engine handoff cases: `10 -> 11`
- PDF engine focused assertions: `95 -> 102`

## Dependency Closure

No new support component is required. The slice reuses native PHP
`PdfEngineHandoff` PDF object, dictionary, value, string-decoding, page-tree,
and fake-runner summary helpers plus the existing WordPress PDF handoff
example.

Follow-up work remains explicitly bounded: geospatial viewport dictionaries
beyond `/Measure` unit metadata, compressed content decoding, full content
stream text extraction/operator sequencing, PDF/A/UA validation, real renderer
execution, and external validator parity.

## Non-overlap

This slice does not repeat accepted sidecar/log/rerun diagnostics, SyncTeX,
`.fls` recorder handoff, transcript include graphs, xref/object streams, page
boxes, page labels, page timings, content streams, fonts, images/form XObject
metadata, document-info/XMP/PDF-A/output-intent/catalog-language/catalog-URI/
tagged-PDF metadata, annotations, RichMedia, attachments, forms, signatures,
optional content groups, encryption preflight, real renderers, or external PDF
validators.

Root harness not run - isolated micro-slice.
