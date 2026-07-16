# Pandoc BibTeX/CSL Legacy Annotation Slice

Slice: `pandoc-bibtex-csl-legacy-annotation-20260615T1244Z`

## Behavior

`BibtexCslProcessor` now preserves bounded BibLaTeX annotation metadata in
legacy CSL handoff items:

- `annotation` and `annote` map to CSL `annotation`.
- `abstract` still wins for CSL `abstract` when present.
- `annote` remains the legacy fallback for `abstract` on annote-only entries.
- Default bibliography text and WordPress bibliography handoff expose annotation
  review text for native PHP reviewer packets.

## Evidence

Red-first focused test failed before implementation because the legacy processor
did not emit CSL `annotation` metadata for BibLaTeX `annotation` fields.

Final verification:

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Result: focused `BibtexCslProcessorTest.php` passed: `1 test files, 271
assertions, 0 failures`. Full `lanes/pandoc/tests` passed: `46 test files,
88677 assertions, 0 failures`.

## Accounting

- `phpPass`: `3733 -> 3734`
- `phpFail` remains `0`
- mapped upstream cases: `3751 -> 3752`
- `mappedBibtexCslProcessorCases`: `7 -> 8`
- `mappedBibtexCslProcessorAnnotationCases`: `1`
- `bibtexCslProcessorAnnotationAssertions`: `13`

## Non-Overlap

This does not repeat the stricter `BibtexCslParser` / `CitationCslProcessor`
annotation normalization, direct CSL JSON abstract/note aliases, translated-title
aliases, original-publication metadata, creator roles, event metadata, or
registry identifier slices. It only fills the legacy `BibtexCslProcessor`
BibLaTeX annotation handoff gap and verifies downstream CSL/WordPress rendering
without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests.
