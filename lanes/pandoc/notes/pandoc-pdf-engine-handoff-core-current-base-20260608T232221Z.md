# Pandoc PDF Engine Handoff Signature Revisions

## Scope

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T232221Z`

This slice adds bounded native fake-runner inspection for produced-PDF digital signature revision provenance. `PdfEngineHandoff` now reports `pdfSignatureRevisionMetadata` and `finalPdfSignatureRevisionMetadata` for field signatures and catalog permission signatures.

Each entry maps the signature `/ByteRange` end to the first trailer/`%%EOF` revision boundary it covers, exposes the matched revision's `startxref`, `/Prev`, `/Root`, `/Info`, and `/Encrypt` metadata, and marks signatures superseded by later incremental revisions. The implementation does not validate cryptographic signatures, decrypt bytes, or execute any PDF engine.

## Evidence

- Rework note check: `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files for this lane.
- Red-first focused command after adding the test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1029 assertions, 1 failures` because `pdfSignatureRevisionMetadata` was absent.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1034 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`.
- Syntax checks passed:
  - `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- JSON validation passed:
  - `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- Diff whitespace check passed:
  - `git diff --check -- lanes/pandoc`
- Root harness: not run - isolated micro-slice.

## Status Delta

- Added 1 mapped PDF engine handoff PASS case.
- Focused `PdfEngineHandoffTest.php` coverage rose from `1023` to `1034` assertions (`+11`).
- `lane-status.json` `phpPass` moves from `1964` to `1965`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` moves from `2385` to `2386`.
- PDF engine inventory moves from `12` to `13` mapped cases and from `108` to `119` assertions.

## Non-Overlap

This slice does not repeat accepted PDF-engine clusters for XMP/PDF-A, output intents, tagged structure, URI base, page display, stream filter policy, stream DecodeParms, embedded-file filter policy, ByteRange policy, crypt filters, page lifecycle actions, StructTreeRoot IDTree, or structure class usage. It only adds trailer-revision provenance for already-discovered signature and catalog-permission ByteRanges in fake-produced PDFs.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `PdfEngineHandoff` object scanning, trailer parsing, signature extraction, catalog permission extraction, and ByteRange handling. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff renderers, external PDF validators, JavaScript execution, online services, live provider tests, and live-service provider tests were not run.

Follow-up can stay local by adding AcroForm signature seed-value dictionaries, DSS/VRI timestamp provenance, or xref repair diagnostics without executing external renderers or validators.
