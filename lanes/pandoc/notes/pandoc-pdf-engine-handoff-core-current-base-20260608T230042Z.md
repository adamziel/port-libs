# Pandoc PDF Engine Handoff Crypt Filters

## Scope

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260608T230042Z`

This slice adds bounded native fake-runner inspection for produced-PDF encryption crypt-filter metadata. `PdfEngineHandoff` now reports `pdfEncryptionDefaultFilters`, `pdfEncryptionCryptFilters`, `finalPdfEncryptionDefaultFilters`, and `finalPdfEncryptionCryptFilters` for `/Encrypt` dictionaries that declare `/StmF`, `/StrF`, `/EFF`, and `/CF` entries.

The metadata includes default stream/string/embedded-file filter names plus each crypt filter's name, referenced object when present, `/CFM`, `/Length`, `/AuthEvent`, bounded `/Recipients` count, and raw dictionary keys. The implementation does not decrypt bytes, derive keys, or execute any PDF engine.

## Evidence

- Rework note check: `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` returned no files for this lane.
- Baseline focused command before this slice: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1011 assertions, 0 failures`.
- Red-first focused command after adding the test: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1014 assertions, 1 failures` because `pdfEncryptionDefaultFilters` was absent.
- Final focused command: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 1023 assertions, 0 failures`.
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
- Focused `PdfEngineHandoffTest.php` coverage rose from `1011` to `1023` assertions (`+12`).
- `lane-status.json` `phpPass` moves from `1950` to `1951`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` moves from `2370` to `2371`.
- PDF engine inventory moves from `12` to `13` mapped cases and from `108` to `120` assertions.

## Non-Overlap

This slice does not repeat accepted PDF-engine clusters for trailer/startxref revisions, xref/object-stream preflight metadata, stream filter policy, decode parameters, XMP/PDF-A/PDF-UA metadata, output intents, tagged structure, destinations, actions, signatures/DSS/ByteRange, AcroForm, optional content, collections, embedded files, rich media, page resources, page display, URI base, or annotations. It only adds document-level encryption default-filter and crypt-filter review metadata for fake-produced PDFs.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `PdfEngineHandoff` object scanning, dictionary parsing, reference resolution, and fake-runner encryption inspection. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff renderers, external PDF validators, JavaScript execution, online services, live provider tests, and live-service provider tests were not run.

Follow-up can stay local by adding xref/object-stream repair diagnostics, incremental signature revision provenance, or encryption handler revision policy without decryption.
