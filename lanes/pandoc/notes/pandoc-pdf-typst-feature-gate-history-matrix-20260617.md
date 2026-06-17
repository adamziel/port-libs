# pandoc-pdf-typst-feature-gate-history-matrix-20260617

Slice: `plib-rwuvm`, PDF/Typst boundary provenance.

Base: `origin/main` at `e67cf503db`.

This slice extends native `PdfEngineHandoff` Typst boundary provenance so the
`feature-gates` matrix case includes CLI feature history and feature override
review details. Invalid `--features` history entries and override policy issues
now contribute to the case issue list while the safe selected `html` and
`packages` feature set remains preserved beside the already-landed
`boundary-overrides`, environment-shadow, and certificate detail matrix cases.

The focused fixture plans a Typst handoff with an unsafe feature token, an empty
feature token, and a later safe override. It verifies the plan matrix, fake-run
artifact provenance review, and final fake-run sequence matrix without invoking
Typst or any PDF engine.

Accounting:

- `mappedTypstBoundaryMatrixCases`: `32 -> 33`
- `typstBoundaryMatrixAssertions`: `316 -> 342`
- `mappedTypstFeatureGateHistoryMatrixCases`: `1`
- `typstFeatureGateHistoryMatrixAssertions`: `26`
- `phpPass`: `17010 -> 17011`
- `phpFail`: `0`
- mapped upstream manifest cases: `16596 -> 16597`
- root mapped inventory: `16565 -> 16566`
- benchmark denominator mapped cases: `3734 -> 3735`

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3148 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258 test files, 175537 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `rg -n '^(<<<<<<<|=======|>>>>>>>)$' lanes/pandoc`
- `git diff --check`

This does not run Pandoc, cmark/commonmark runners, Cabal/Haskell runners,
Typst, TeX/PDF engines, browser renderers, Node tooling, external validators,
online services, live provider tests, or live-service provider tests.
