# PDF Engine Handoff Core Current Base - TeX Artifact Stem Planning

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260609T070257Z`
Base accepted HEAD: `53cc273b044292e061f08ae6f6fdabc37210dcb0`
Date: 2026-06-09 UTC

## Behavior

`PdfEngineHandoff::plan()` now derives a bounded TeX-family artifact stem from
safe `-jobname` and `-output-directory` / `-outdir` engine options. The planner
uses that stem for expected log, aux, recorder, and SyncTeX paths, and
`fakeRun()` recognizes redirected bibliography sidecars such as `.bcf`, `.bbl`,
`.blg`, and `.run.xml` under the same stem.

This keeps WordPress review packets able to triage renderer logs, recorder
dependency files, source maps, and bibliography sidecars when Pandoc users pass
PDF-engine options that move TeX artifacts outside the intermediate source
directory. It is a planning/fake-runner handoff only.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external
template engine, external converter, TeX/PDF engine, Typst, browser renderer,
online service, live provider test, or live-service provider test was executed.

## Evidence

Baseline focused test before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1343 assertions, 0 failures`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 1358 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
- Result: `pdf engine handoff self-test ok`

Additional syntax and whitespace verification is recorded in the final handoff
output.

## Status Delta

- `lane-status.json` `phpPass`: `2467 -> 2468`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2851 -> 2852`.
- `pdfEngineHandoffCoreCases`: `12 -> 13`.
- `mappedPdfEngineHandoffCoreCases`: `12 -> 13`.
- `pdfEngineHandoffCoreAssertions`: `108 -> 123`.

## Non-Overlap

This slice avoids the prior PDF fake-runner work for missing executable
diagnostics, missing TeX/font dependency extraction, engine warnings/errors,
rerun clearing, recorder parsing itself, SyncTeX parsing itself, PDF byte
inspection, catalog requirements, PDF/A/PDF/UA policy, DSS/VRI, legal
attestation, web capture, linearization, xref/object streams, page/action/form/
signature/annotation metadata, and resource-file hashing. It owns only the
artifact-stem planning and fake-runner sidecar recognition needed when TeX
engine options redirect sidecar names or directories.

## Dependency Closure

No new native support component is needed. This reuses the existing
`PdfEngineHandoff` planner, fake-runner artifact hashing, recorder parser,
SyncTeX planner, bibliography sidecar recognition, `MarkdownReader`,
`LatexWriter`, WordPress PDF handoff example, and focused TestRunner.

Full renderer parity remains outside this slice and would require explicit
authorization for TeX/PDF engines, Typst, browser renderers, roff, external
converters, or upstream Pandoc/Haskell runner work.
