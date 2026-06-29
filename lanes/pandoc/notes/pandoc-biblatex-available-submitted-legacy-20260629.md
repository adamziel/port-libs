# Pandoc BibLaTeX Available Submitted Legacy Handoff

Slice: `pandoc-biblatex-available-submitted-legacy-20260629`
Issue: `plib-p8ei9`

## Behavior

`BibtexCslProcessor` now preserves legacy BibLaTeX availability and submission
date metadata as CSL-shaped dates:

- `availabledate` / `available-date` literal dates and date ranges.
- `availableyear`, `availablemonth`, `availableday` split date fields.
- `submitted` / `submitteddate` / `submitted-date` literal dates.
- `submittedyear`, `submittedmonth`, `submittedday` plus split end-date
  fields.

The legacy handoff carries those values through direct CSL item arrays, default
bibliography review text, `CitationCslProcessor` date rendering,
`citationHandoff()`, and WordPress bibliography output.

## Evidence

```text
php -l lanes/pandoc/src/BibtexCslProcessor.php
No syntax errors detected in lanes/pandoc/src/BibtexCslProcessor.php

php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
No syntax errors detected in lanes/pandoc/tests/BibtexCslProcessorTest.php

php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
1 test files, 832 assertions, 0 failures
```

## Non-Overlap

This does not repeat the accepted `BibtexCslParser` split end-date slice or the
existing direct CSL available/submitted date renderer. It only closes the legacy
`BibtexCslProcessor` import and review-handoff path for already supported CSL
date variables.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external bibliography
manager, online service, live provider test, or live-service provider test was
executed.
