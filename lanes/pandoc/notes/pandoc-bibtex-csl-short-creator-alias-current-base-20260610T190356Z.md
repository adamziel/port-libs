# Pandoc BibTeX/CSL Short Creator Alias Slice

Date: 2026-06-10 UTC
Base: af490fd5cae1540ef70032dc4f486d43497e6c71
Micro-slice: plib-8mt7n / pandoc-bibtex-csl-short-creator-alias-current-base-20260610T190356Z

## Scope

This slice maps one bounded BibLaTeX creator alias cluster in the legacy
BibTeX/CSL handoff. `shortauthor`, `shorteditor`, and `holder` now normalize to
CSL `short-author`, `short-editor`, and `holder` name variables while preserving
the raw BibLaTeX fields for reviewer provenance.

## Evidence

Final focused verification:

```text
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
1 test files, 100 assertions, 0 failures
```

Full lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 61068 assertions, 0 failures
```

Bookkeeping delta:

- `phpPass`: `2999 -> 3000`
- `benchmarkDenominator.mapped`: `3155 -> 3156`
- `mappedBibtexCslShortCreatorAliasCases`: `1`
- `bibtexCslShortCreatorAliasAssertions`: `10`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP BibTeX
parser, CSL item normalization, name parser, and bibliography renderer. It does
not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, Cabal/Haskell
runners, office suites, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat accepted BibLaTeX entry-alias, extended creator-role,
direct camelCase creator, original-publication, authority-identifier, or
eprint/access metadata slices. It is limited to legacy short creator and holder
name aliases in the BibTeX/CSL handoff.
