# pandoc-pdf-typst-package-cache-path-alias-20260617

Slice: `plib-6xm5i`, PDF/Typst boundary provenance.

Base: `origin/main` at `4cc5be1743`.

This recovery slice restores focused coverage for safe Typst
`--package-cache-path` alias provenance. Current `PdfEngineHandoff` already
parses `--package-cache-path` with `--package-cache`; the fixture verifies the
safe relative alias together with `--package-path` across the plan, fake-run
artifact review, and fake-run sequence summaries.

Accounting:

- `mappedTypstPackageCachePathAliasCases`: `1`
- `typstPackageCachePathAliasAssertions`: `8`
- `phpPass`: `17005 -> 17006`
- `phpFail`: `0`
- mapped upstream manifest cases: `16591 -> 16592`
- root mapped inventory: `16560 -> 16561`
- benchmark denominator mapped cases: `3729 -> 3730`

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 3034 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258 test files, 175423 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `rg -n '^(<<<<<<<|=======|>>>>>>>)$' lanes/pandoc`
- `git diff --check`

This does not run Pandoc, cmark/commonmark runners, Cabal/Haskell runners,
Typst, TeX/PDF engines, browser renderers, Node tooling, external validators,
online services, live provider tests, or live-service provider tests.
