# pandoc-bibtex-csl-event-source-extent-current-base-20260610T121017Z

Slice: `pandoc-bibtex-csl-event-source-extent-current-base-20260610T121017Z`

## Summary

Mapped one bounded legacy BibTeX/BibLaTeX CSL handoff case for reviewer
event/source/extent metadata:

- `reviewtitle` plus `reviewsubtitle` now become `reviewed-title`.
- `source`, `eventtitle`, `eventtitleaddon`, `eventvenue`, `eventdate`, and
  `eventtype` now survive as CSL review metadata.
- `chapter`, `section`, `pagetotal`, `volumes`, `eid`, `pagination`, and
  `bookpagination` now survive as extent/publication metadata.

The slice stays under `lanes/pandoc`, does not shell out to Pandoc, BibTeX,
Biber, citeproc, Cabal/Haskell runners, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

## Evidence

- Red-first focused run after adding the test failed on missing
  `reviewed-title`: `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  reported `1 test files, 92 assertions, 1 failures`.
- Final focused run passed:
  `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  reported `1 test files, 105 assertions, 0 failures`.
- Syntax checks passed for `BibtexCslProcessor.php` and
  `BibtexCslProcessorTest.php`.
- Full Pandoc PHP gate passed:
  `php tools/run-tests.php lanes/pandoc/tests`
  reported `44 test files, 59896 assertions, 0 failures`.

## Accounting

- `lane-status.json` `phpPass`: `2960 -> 2961`.
- Focused PHP assertions in `BibtexCslProcessorTest.php`: `92 -> 105`.
- Mapped denominator: `3130 -> 3131`.
- Added one mapped `BibtexCslProcessor` event/source/extent metadata case.
