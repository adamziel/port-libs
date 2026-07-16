# Pandoc Direct CSL Title Text Family Alias Precedence

Slice: `pandoc-csl-title-text-family-alias-precedence-20260613T093300Z`

Rebased verification base: current main `b1b0055fb3`.

Implemented a bounded native PHP Citation/CSL follow-up for direct CSL JSON
title-family text aliases:

- `mainTitleText`, `main-title-text`, `maintitletext`
- `volumeTitleText`, `volume-title-text`, `volumetitletext`
- `partTitleText`, `part-title-text`, `parttitletext`

The aliases normalize into canonical `mainTitle`, `volumeTitle`, and
`partTitle` metadata with deterministic hyphen-form precedence when multiple
alias spellings are present. The slice also renders the `*-title-text` CSL text
variables and uses normalized `part-title` metadata for citation and
bibliography sort keys.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5481 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 79658 assertions, 0 failures`
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- conflict-marker scan over touched files

## Accounting

- New focused case: `normalizes bounded direct csl json title text family alias precedence`
- `phpPass`: `3429 -> 3430`
- Direct CSL mapped cases: `mappedCslDirectTitleTextFamilyAliasCases = 1`
- Focused assertions added: `cslDirectTitleTextFamilyAliasAssertions = 28`
