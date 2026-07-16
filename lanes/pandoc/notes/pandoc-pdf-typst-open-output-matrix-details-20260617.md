# pandoc-pdf-typst-open-output-matrix-details-20260617

Slice: `plib-utuux`, PDF/Typst boundary provenance.

Base: `origin/main` at `e9c706898b`.

This slice extends native `PdfEngineHandoff` Typst boundary matrix provenance so
the `open-output` matrix case carries reviewer-facing viewer details. The case
now reports invalid viewer count, final viewer, distinct viewer names, and raw
viewer values while preserving the existing open-output side-effect review
issues.

The focused fixture plans mixed `--open` values with a valid viewer, an empty
viewer boundary, and a final specific viewer. It verifies plan matrix details,
fake-run artifact provenance review, and final fake-run sequence matrix without
invoking Typst or any PDF engine.

Accounting:

- `mappedTypstBoundaryMatrixCases`: `35 -> 36`
- `typstBoundaryMatrixAssertions`: `404 -> 425`
- `mappedTypstOpenOutputMatrixDetailCases`: `1`
- `typstOpenOutputMatrixDetailAssertions`: `21`
- `phpPass`: `17014 -> 17015`
- `phpFail`: `0`
- mapped upstream manifest cases: `16600 -> 16601`
- root mapped inventory: `16569 -> 16570`
- benchmark denominator mapped cases: `3738 -> 3739`

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3227 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258 test files, 175616 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `rg -n '^(<<<<<<<|=======|>>>>>>>)$' lanes/pandoc`
- `git diff --check`

This does not run Pandoc, cmark/commonmark runners, Cabal/Haskell runners,
Typst, TeX/PDF engines, browser renderers, Node tooling, external validators,
online services, live provider tests, or live-service provider tests.
