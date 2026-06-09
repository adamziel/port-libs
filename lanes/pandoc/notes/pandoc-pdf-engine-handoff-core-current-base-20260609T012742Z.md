## PDF Engine Signature Seed Values

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T012742Z`
Base: `942d0b99001290be4ad52e5f31464bd1e4c71c99`

Implemented one bounded produced-PDF handoff cluster: AcroForm signature field `/SV` seed-value dictionaries. The fake runner now resolves referenced and inline seed-value dictionaries and reports reviewer metadata for required filter/subfilter/digest choices, minimum PDF version, reasons, legal attestations, MDP permissions, timestamp URL/required flag, and AddRevInfo.

Focused evidence:

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 1086 assertions, 0 failures`.
- Red-first: the same focused command failed as expected with `1 test files, 1088 assertions, 1 failures` because `pdfSignatureSeedValues` was missing.
- Final: `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed with `1 test files, 1102 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test` passed.

Status delta:

- Adds one mapped native PDF engine support case.
- Adds one focused PHP PASS case and 16 focused assertions.
- `lane-status.json` `phpPass` moves from `2037` to `2038`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped` moves from `2451` to `2452`.

Dependency closure:

No new support component is needed. This reuses the native `PdfEngineHandoff` fake-runner PDF object/value parser, bounded AcroForm/signature metadata extraction, focused PDF tests, and the lane-local WordPress PDF handoff example. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, signing engines, online services, live provider tests, and live-service provider tests were not executed.

Non-overlap:

This does not repeat accepted PDF signature byte ranges, signature revision metadata, catalog permission signatures, DSS, encryption, PDF/A, PDF/UA, PDF/X, output-intent, URI-base, page display, tagging, or active action handoffs. It is limited to signature seed-value review metadata.

Follow-up:

Potential follow-up work should stay non-overlapping, such as seed-value lock-document interaction diagnostics, field permission transform cross-checks, or remaining catalog/form review metadata.
