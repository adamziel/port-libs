# pandoc-biblatex-legacy-thesis-aliases-20260615T0950Z

Mapped one bounded CSL/BibTeX core blocker case in the legacy
`BibtexCslProcessor` handoff after rebase onto current main `a088c813cd`.

## Scope

- Carry BibLaTeX `school` as CSL publisher metadata in the legacy processor.
- Recognize `@mathesis` as CSL `thesis`.
- Preserve `thesistype`, `thesis-type`, and thesis-entry `type` values as
  `thesis-type` metadata.
- Keep thesis type visible in legacy bibliography text, styled CSL rendering,
  citation handoff metadata, and WordPress bibliography blocks.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Accounting

- `phpPass`: 3710 -> 3711
- `phpFail`: 0
- Upstream mapped manifest cases: 3733 -> 3734
- `mappedBibtexCslProcessorCases`: 7 -> 8
- `mappedBibtexCslProcessorThesisAliasCases`: 1
- `bibtexCslProcessorThesisAliasAssertions`: 23

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 file, 281 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 87915 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- Conflict-marker scan across touched Pandoc files
