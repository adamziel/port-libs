# Pandoc BibTeX/CSL Legacy Label Date Slice

Implemented a bounded native PHP CSL/BibLaTeX handoff for legacy `BibtexCslProcessor`
label-date metadata.

The slice maps scalar `labeldate` / `label-date` fields and split `labelyear`,
`labelmonth`, `labelday` aliases into CSL `label-date` date parts. The metadata
now flows through direct bibliography review text, `CitationCslProcessor`
normalization, CSL style date rendering, citation handoff items, and WordPress
bibliography output.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- Result: `1 test files, 827 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite, browser,
TeX/PDF engine, online service, or external validator was invoked.

This does not repeat accepted direct `BibtexCslParser` label-date handling,
available/submitted dates, date addenda, date marker/time/season handling, or
title/identifier/provenance metadata slices. It only fills the legacy
`BibtexCslProcessor` path for CSL `label-date` preservation.
