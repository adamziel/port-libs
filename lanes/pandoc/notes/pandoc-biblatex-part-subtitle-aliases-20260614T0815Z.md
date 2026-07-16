# Pandoc BibLaTeX Part Subtitle Aliases

Slice: `pandoc-biblatex-part-subtitle-aliases-20260614T0815Z`

Rebased verification base: current main `e273ca40f3`.

Implemented a bounded native PHP Citation/CSL slice for parsed BibTeX and
BibLaTeX part subtitle aliases:

- Compact `parttitle` plus `partsubtitle`
- Hyphenated `part-title` plus `part-subtitle`

`BibtexCslParser` now composes those aliases into canonical CSL `part-title`
metadata. The focused coverage verifies raw BibTeX field provenance, normalized
`partTitle` metadata, default bibliography fallback text, explicit CSL style
rendering, bibliography entries, and WordPress bibliography output.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, Cabal/Haskell runner,
browser renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5606 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 82072 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`

## Accounting

- New focused case: `composes bounded biblatex part subtitle aliases into csl part titles`
- `phpPass`: `3491 -> 3492`
- `phpFail`: `0`
- Upstream mapped denominator: `3420 -> 3421`
- `mappedBiblatexPartSubtitleAliasCases = 1`
- `biblatexPartSubtitleAliasAssertions = 16`
