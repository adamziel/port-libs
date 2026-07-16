# pandoc-pdf-typst-boundary-provenance-current-base-20260610T124056Z

Slice: `pandoc-pdf-typst-boundary-provenance-current-base-20260610T124056Z`
Bead: `plib-c3kk`
Date: 2026-06-10 UTC

## Behavior

`PdfEngineHandoff` now records declared Typst `--root` values in PDF engine
plans, fake-run results, artifact provenance review metadata, and fake-run
sequence summaries.

Typst dependency sidecar inputs remain parsed from bounded make-style depfiles,
but local inputs are now checked against the declared root. A fake run fails
with `engine-boundary-violation` when a depfile names a local input outside the
root, even when that file is present in the fake runner file map. This keeps
boundary escapes visible as provenance issues instead of treating them as
ordinary successful local inputs.

No Pandoc, Typst, TeX/PDF engine, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service provider
test is executed.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3026 -> 3027`.
- Rebased on current `origin/main` `e7815c51c317703f8adf488b8c2647a84f776370`
  while preserving the new root-boundary violation coverage.
- `UPSTREAM_TEST_MANIFEST.json` remains on current `main` counters for this
  slice; no direct-format denominator update is claimed by this refresh.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `git diff --check`
- exact conflict-marker scan
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 1571 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed with `44 test files, 61700 assertions, 0 failures`.

## Scope

This slice only changes PDF/Typst fake-runner boundary provenance. It does not
touch Markdown/plain/CommonMark, HTML microdata, wiki, roff, media-bag, EPUB,
ODF, DOCX, CSL, JSON/native AST, or ZIP/OPC behavior.
