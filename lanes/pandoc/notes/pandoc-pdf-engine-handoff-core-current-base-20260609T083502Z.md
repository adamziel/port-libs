# Pandoc PDF Engine Handoff URI Active Actions

Session: `port-dev-pandoc-pdf-handoff-20260609T083502Z`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T083502Z`
Base: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

`PdfEngineHandoff` now treats produced-PDF `/S /URI` action dictionaries as bounded active actions. The fake runner records URI targets for catalog `OpenAction`, page additional actions, annotation `A`/`AA` dictionaries, and existing action chains.

The handoff is metadata-only. It exposes URI targets in `pdfActiveActions`, page lifecycle URI actions in `pdfPageActions`, and review policy issue `uri-action` alongside existing remote-target diagnostics. No PDF renderer, browser, TeX engine, Typst engine, external validator, or online service is executed.

## Verification

- Baseline before adding the red test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` => `1 test files, 1376 assertions, 0 failures`.
- Red-first after adding the URI case: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` => `1 test files, 1379 assertions, 1 failures`; the missing behavior was empty `pdfActiveActions` for `/S /URI` dictionaries.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` => `1 test files, 1394 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` => `pdf engine handoff self-test ok`.
- PHP lint: `php -l lanes/pandoc/src/PdfEngineHandoff.php`, `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` all reported no syntax errors.
- JSON validation: `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` decoded with `JSON_THROW_ON_ERROR`.
- Diff hygiene: `git diff --check -- lanes/pandoc` => clean.

## Movement

- `phpPass`: `2530` -> `2531`.
- `benchmarkDenominator.mapped`: `2898` -> `2899`.
- `pdfEngineHandoffCoreCases`: `12` -> `13`.
- `mappedPdfEngineHandoffCoreCases`: `12` -> `13`.
- `pdfEngineHandoffCoreAssertions`: `108` -> `126`.

## Non-Overlap

This slice does not repeat existing PDF handoff coverage for JavaScript, Launch, SubmitForm, ResetForm, ImportData, page lifecycle action summaries, named destinations, AcroForm submit/export policy, signature byte ranges, stream filters, structure trees, optional content, DSS/VRI metadata, or renderer failure diagnostics. It owns only the bounded `/S /URI` active-action handoff gap.

## Dependency Closure

No new native support component is needed. The change reuses the existing `PdfEngineHandoff` PDF object/dictionary parser, named-string extractor, active-action scanner, page-action policy summarizer, fake-runner result wiring, and WordPress PDF review-packet example.

## Next

Next PDF-engine work should choose a non-overlapping native fake-runner/provenance gap such as bounded file-attachment action policy, viewer-preference edge cases, optional-content policy handoff, or PDF/A/PDF/UA conformance hint review. Continue avoiding Pandoc, Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, Typst, browser renderers, external PDF validators, online services, and live providers.
