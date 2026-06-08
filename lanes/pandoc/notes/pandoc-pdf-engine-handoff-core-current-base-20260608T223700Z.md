# Pandoc PDF Engine Handoff Object-Stream Member Provenance

## Scope

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T223700Z`

This slice adds bounded native fake-runner inspection for produced-PDF object-stream member provenance. `PdfEngineHandoff` now reports `pdfObjectStreamMembers` and `finalPdfObjectStreamMembers` for unfiltered `/ObjStm` streams, including compressed object number, member index, declared header offset, decoded stream offset, member byte count/hash, value kind, dictionary keys, `/Type`, `/Subtype`, and `/Title`.

Filtered object streams remain metadata-only and fail closed for member extraction because this lane must not shell out to PDF validators, renderers, or external stream decoders.

## Evidence

- Rework note check: `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files for this lane.
- Baseline focused command before this slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 998 assertions, 0 failures`.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1011 assertions, 0 failures`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added 1 mapped PDF-engine handoff PASS case.
- Focused `PdfEngineHandoffTest.php` coverage rose from `998` to `1011` assertions (`+13`).
- `lane-status.json` `phpPass` moves from `1934` to `1935`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` moves from `2355` to `2356`.
- PDF-engine inventory moves from `12` to `13` mapped cases and from `108` to `121` assertions.

## Non-Overlap

This slice does not repeat accepted PDF-engine clusters for trailer/startxref revisions, xref/object-stream preflight metadata, stream filter policy, decode parameters, XMP/PDF-A/PDF-UA metadata, output intents, tagged structure, destinations, actions, signatures/DSS/ByteRange, AcroForm, optional content, collections, embedded files, rich media, page resources, or annotations. It only adds per-member provenance for object bodies already packed into unfiltered fake-produced `/ObjStm` streams.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `PdfEngineHandoff` object scanning, bounded stream extraction, PDF value parsing, and dictionary-entry helpers. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript execution, online services, live provider tests, and live-service provider tests were not run.

Follow-up can stay local by adding Crypt-filter permission detail, incremental signature revision provenance, or xref/object-stream repair diagnostics inside the same fake-runner boundary.
