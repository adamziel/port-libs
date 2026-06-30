# Pandoc BibTeX/CSL Reprint Title Original Genre Handoff

Slice: `pandoc-bibtex-csl-reprint-original-genre-20260629`
Bead: `plib-f1l4w`

## Behavior

`BibtexCslProcessor` now maps legacy BibLaTeX reprint and original-source
provenance fields into bounded CSL metadata:

- `reprinttitle` / `reprint-title` map to CSL `reprint-title`.
- `origtype`, `origgenre`, `originaltype`, `original-type`,
  `originalgenre`, and `original-genre` map to CSL `original-genre`.
- Direct bibliography review text exposes both fields.
- `CitationCslProcessor` styled text variables can render the metadata.
- Citation handoff and WordPress bibliography output preserve the fields without
  invoking external citeproc.

This closes a legacy processor gap: `BibtexCslParser` and
`CitationCslProcessor` already understood these variables, but the older
`BibtexCslProcessor` path dropped them.

## Evidence

Syntax checks:

```text
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
```

Focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
1 test files, 944 assertions, 0 failures
```

## Accounting

- `lane-status.json` `phpPass`: `465 -> 466`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2307 -> 2308`
- Added `mappedLegacyBiblatexReprintOriginalGenreCases: 1`

## Non-Overlap

This slice does not repeat accepted original-title/date/publisher/language,
date-addendum, author-type, event metadata, reviewed-work, series creator,
editor-translator, legal authority, or source-file attachment slices. It only
adds the missing legacy BibLaTeX import path for `reprint-title` and
`original-genre` provenance.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
TeX/PDF engine, office suite, external validator, online service, or live
provider test was executed.
