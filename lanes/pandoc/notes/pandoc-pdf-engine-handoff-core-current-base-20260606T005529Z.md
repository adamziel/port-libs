# Pandoc PDF Engine Handoff Core Current Base 20260606T005529Z

Lane: `pandoc`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T005529Z`
Accepted base: `ff7d31e1397095949e33524eafeb5b7160ae8790`

## Scope

This slice extends the bounded native PHP PDF-output fake-runner handoff. It
extracts detailed produced-PDF page annotation metadata without invoking
Pandoc, TeX/PDF engines, Typst, browser renderers, roff, external PDF
validators, JavaScript, online services, Word, LibreOffice, zip/unzip, or live
provider tests.

The non-overlap target is annotation detail handoff:

- Page tree traversal for `/Annots` arrays.
- Referenced and inline annotation dictionaries.
- Annotation subtype, page/object provenance, and geometry.
- `/Rect`, `/QuadPoints`, `/Contents`, `/T`, `/NM`, `/M`, `/Name`, `/C`,
  `/Border`, and `/F` flag-name handoff.
- Bounded action and destination summaries for annotation `/A` and `/Dest`.

This does not repeat accepted PDF handoff coverage for sidecars, logs, SyncTeX,
FLS, transcripts, xref/object streams, page boxes, rotations, fonts, images,
form XObjects, page labels, page timing, document info, XMP, PDF/A, output
intents, catalog presentation, named destinations, tagging, structure
elements, aggregate annotation type/link counts, embedded files, collections,
threads, AcroForm fields, active actions, JavaScript hashes, optional content,
PieceInfo, signatures, or encryption preflight.

## Implementation

- Added `pdfAnnotations` to `PdfEngineHandoff::fakeRun()`.
- Added `finalPdfAnnotations` to `PdfEngineHandoff::fakeRunSequence()`.
- Added bounded annotation extraction helpers for page-tree annotation arrays,
  inline annotation dictionaries, annotation action summaries, destination
  arrays, number-array values, and PDF annotation flag names.
- Updated the WordPress PDF handoff example self-test to expose concrete
  annotation detail metadata and diagnostics.
- Updated lane status and upstream manifest counters for one new focused PDF
  engine handoff case.

Status movement recorded in lane files:

- `phpPass`: `1125` to `1126`.
- `benchmarkDenominator.mapped`: `1577` to `1578`.
- PDF engine handoff core cases: `10` to `11`.
- PDF engine handoff core assertions: `95` to `104`.

## Evidence

Red-first focused check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 532 assertions, 1 failures`.

The failing assertion showed that `pdfAnnotations` was absent from the fake
runner output.

Green focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 539 assertions, 0 failures`.

Additional verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`: no syntax errors.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`: no syntax errors.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `pandoc JSON ok`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`: `pdf engine handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded
native PHP PDF object, dictionary, scalar, action, destination, and page-tree
inspection helpers inside `PdfEngineHandoff`.

Remaining out-of-scope follow-ups are annotation appearance streams, popup and
reply relationships, rich-media/sound/movie annotation payload policy,
compressed object stream expansion, full PDF/A validator parity, and real
renderer parity.

Root harness: not run - isolated micro-slice.
