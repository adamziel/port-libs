# Pandoc BibTeX/CSL xref summary parity

Slice: `plib-cbtjg`

## Summary

`BibtexCslProcessor` now preserves BibLaTeX `xref` reference metadata in the
legacy direct CSL handoff. The direct path already kept the raw `xref` field;
this slice adds resolved `xrefKeys`, `xrefItems`, `xrefSummary`, and
`missingXrefKeys`, plus bibliography text output for the xref parent summary.

This closes parity with the newer `BibtexCslParser` /
`CitationCslProcessor::fromBibtex()` pathway, where bounded `xref` metadata was
already normalized and rendered without inheriting parent fields.

## Validation

```sh
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
```

Results:

- `BibtexCslProcessorTest.php`: `1 test files, 1144 assertions, 0 failures`
- `CitationCslProcessorTest.php`: `1 test files, 6112 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
online services, or external validators were invoked.

## Accounting

- Direct legacy BibTeX/CSL handoff case added: BibLaTeX `xref` resolved
  reference summaries.
- Direct-format parity: legacy `BibtexCslProcessor::cslItems()` now matches the
  existing `CitationCslProcessor::bibtexItems()` xref metadata surface.
