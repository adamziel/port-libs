# Pandoc PDF Engine Handoff Core Current Base 20260605T141415Z

Lane: `pandoc`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T141415Z`
Accepted base: `e84d1d99a4f722d572b5331476b61b2ce23c0632`

## Scope

This slice extends the bounded native PHP PDF-output fake-runner handoff. It
extracts produced-PDF page display timing metadata without invoking Pandoc,
TeX, Typst, browser engines, roff, external validators, online services, Word,
LibreOffice, or archive tools.

The non-overlap target is page-level slideshow/review metadata:

- `/Dur` page display durations.
- `/Trans` page transition dictionaries, direct or indirect.
- Transition `/S`, `/D`, `/Di`, `/Dm`, `/M`, `/SS`, and `/B` values.
- Diagnostics for page timing count, duration count, transition count, and
  transition types.

This does not repeat accepted PDF handoff coverage for sidecars, logs, SyncTeX,
FLS, transcripts, xref/object streams, page boxes, rotations, fonts, images,
page labels, document info, XMP, PDF/A, output intents, catalog presentation,
named destinations, tagging, structure elements, annotations, embedded files,
AcroForm fields, active actions, JavaScript hashes, or encryption preflight.

## Implementation

- Added `pdfPageTimings` to `PdfEngineHandoff::fakeRun()`.
- Added `finalPdfPageTimings` to `PdfEngineHandoff::fakeRunSequence()`.
- Reused the existing PDF object and dictionary helpers to walk the page tree,
  resolve direct and indirect `/Trans` dictionaries, and summarize timing
  metadata.
- Updated the WordPress PDF handoff example self-test to expose the timing
  handoff fields and diagnostics.
- Updated lane status and upstream manifest counters for one new focused PDF
  engine handoff case.

Status movement recorded in lane files:

- `phpPass`: `939` to `940`.
- `benchmarkDenominator.mapped`: `1395` to `1396`.
- PDF engine handoff core cases: `10` to `11`.
- PDF engine handoff core assertions: `95` to `103`.

## Evidence

Red-first focused check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 429 assertions, 1 failures`.

Green focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 437 assertions, 0 failures`.

Additional verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`: no syntax errors.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`: no syntax errors.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `json ok`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`: `pdf engine handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded native
PHP PDF dictionary/object inspection helpers in `PdfEngineHandoff`.

Remaining out-of-scope follow-ups are real renderer parity, richer transition
normalization, marked-content/MCID correlation, PDF/UA-specific checks,
OCG/layer metadata, signatures, and upstream-runner hydration evidence.

Root harness: not run - isolated micro-slice.
