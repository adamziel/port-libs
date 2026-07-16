# Pandoc PDF Engine Handoff Core Current Base 20260606T002209Z

Lane: `pandoc`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T002209Z`
Accepted base: `8d8a80a78390b6509e16804d31b716ba9f76aa38`

## Scope

This slice extends the bounded native PHP PDF-output fake-runner handoff. It
extracts produced-PDF `/PieceInfo` private metadata without invoking Pandoc,
TeX/PDF engines, Typst, browser renderers, roff, external PDF validators,
online services, Word, LibreOffice, zip/unzip, or live provider tests.

The non-overlap target is produced-PDF application provenance:

- Catalog-level `/PieceInfo` dictionaries.
- Page-level `/PieceInfo` dictionaries reached through the page tree.
- Application names, piece object references, and `/LastModified` values.
- Bounded `/Private` dictionary scalar values.
- Bounded unfiltered `/Private` stream byte counts and SHA-256 hashes.
- Skip diagnostics for filtered, missing, or oversized private streams.

This does not repeat accepted PDF handoff coverage for sidecars, logs, SyncTeX,
FLS, transcripts, xref/object streams, page boxes, rotations, fonts, images,
form XObjects, page labels, page timing, document info, XMP, PDF/A, output
intents, catalog presentation, named destinations, tagging, structure elements,
annotations, embedded files, collections, threads, AcroForm fields, active
actions, JavaScript hashes, optional content, signatures, or encryption
preflight.

## Implementation

- Added `pdfPieceInfo` to `PdfEngineHandoff::fakeRun()`.
- Added `finalPdfPieceInfo` to `PdfEngineHandoff::fakeRunSequence()`.
- Reused the existing native PDF object, dictionary, stream, scalar, catalog,
  and page-tree inspection helpers to summarize bounded PieceInfo metadata.
- Updated the WordPress PDF handoff example self-test to expose PieceInfo
  handoff fields and diagnostics.
- Updated lane status and upstream manifest counters for one new focused PDF
  engine handoff case.

Status movement recorded in lane files:

- `phpPass`: `1119` to `1120`.
- `benchmarkDenominator.mapped`: `1571` to `1572`.
- PDF engine handoff core cases: `10` to `11`.
- PDF engine handoff core assertions: `95` to `103`.

## Evidence

Baseline focused check before this slice:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 522 assertions, 0 failures`.

Green focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Result: `1 test files, 530 assertions, 0 failures`.

Additional verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`: no syntax errors.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`: no syntax errors.
- JSON validation for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: `pandoc JSON ok`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`: `pdf engine handoff self-test ok`.
- `git diff --check -- lanes/pandoc`: no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded native
PHP PDF inspection helpers inside `PdfEngineHandoff`.

Remaining out-of-scope follow-ups are real renderer parity, marked-content
property dictionary correlation, richer PDF/A validator parity, compressed
private PieceInfo stream policy, and upstream-runner hydration evidence.

Root harness: not run - isolated micro-slice.
