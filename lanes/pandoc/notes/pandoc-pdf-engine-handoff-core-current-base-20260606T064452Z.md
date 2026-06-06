# Pandoc PDF Engine Handoff Current Base

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T064452Z`
- Accepted base: `efaf7892c3f0240c764f0fe029726e5aaf7397ce`
- Date: 2026-06-06 UTC

## Scope

This slice adds bounded native PHP handoff diagnostics for fake-produced PDF page
`/Contents` streams. It does not implement or invoke TeX, Typst, browser,
roff, external PDF validators, Pandoc, Cabal, Haskell runners, JavaScript,
online sanitizers, online services, or live provider tests.

## Behavior

`PdfEngineHandoff::fakeRun()` now includes `pdfPageContentStreams` and
`pdfPageContentResourceUsage` when produced PDF bytes are supplied.
`PdfEngineHandoff::fakeRunSequence()` carries the final-pass equivalents as
`finalPdfPageContentStreams` and `finalPdfPageContentResourceUsage`.

For unfiltered page content streams, the summary records:

- page object and content object references;
- stream byte length and SHA-256 hash;
- text object counts from `BT`;
- XObject image/form paint counts from `/Name Do` and page resources;
- marked-content begin/end counts from `BMC`/`BDC` and `EMC`;
- MCID values, marked-content property names, resource names, and resource usage.

Filtered content streams remain bounded: they are identified with their filter
names and `streamSkipped: filtered` rather than decoded.

## Evidence

- Baseline before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 589 assertions, 0 failures`
- Red-first after adding the focused case:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 591 assertions, 1 failures`
  - The failure was the missing marked-content property name for `/Span ... BDC`.
- Final focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  -> `1 test files, 601 assertions, 0 failures`
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

- `lanes/pandoc/lane-status.json` `phpPass`: `1232 -> 1233`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1675 -> 1676`
- PDF engine handoff cases: `10 -> 11`
- Mapped PDF engine handoff cases: `10 -> 11`
- PDF engine focused assertions: `95 -> 107`

## Dependency Closure

No new support component is required. The slice reuses native PHP
`PdfEngineHandoff` PDF object, dictionary, page-tree, stream, and fake-runner
summary helpers plus the existing WordPress PDF handoff example.

Follow-up work remains explicitly bounded: compressed content stream decoding,
full content-stream text extraction/operator sequencing, Optional Content
marked-content correlation, PDF/A/UA validation, real renderer execution, and
external validator parity.

## Non-overlap

This slice does not repeat accepted sidecar/log/rerun diagnostics, SyncTeX,
`.fls` recorder handoff, transcript include graphs, xref/object streams, page
boxes, fonts, images/form XObject metadata, page labels, timings,
document-info/XMP/PDF-A/output-intent/catalog-language/catalog-URI/tagged-PDF
metadata, annotations, RichMedia, attachments, forms, signatures, optional
content groups, encryption preflight, real renderers, or external PDF
validators.

Root harness not run - isolated micro-slice.
