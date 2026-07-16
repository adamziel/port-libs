# Pandoc BibTeX/CSL Event Metadata Slice

Date: 2026-06-10 UTC
Base after rebase: fb403f2254963bf4d116cb47fae0b415a7e0599d
Micro-slice: plib-pj956 / pandoc-bibtex-csl-event-metadata-current-base-20260610T194310Z

## Scope

This slice maps one bounded BibLaTeX event metadata cluster in the legacy
`BibtexCslProcessor` handoff. `eventtitle`, `eventtitleaddon`, `eventtype`,
`eventdate`, `venue`, and `eventorganizer` now normalize to CSL `event`,
`event-title-addon`, `event-type`, `event-date`, `event-place`, and
`event-organizer` variables while preserving raw BibLaTeX field provenance.

## Evidence

Final focused verification:

```text
php -l lanes/pandoc/src/BibtexCslProcessor.php
php -l lanes/pandoc/tests/BibtexCslProcessorTest.php
php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php
1 test files, 112 assertions, 0 failures
```

Full lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 61307 assertions, 0 failures
```

Bookkeeping delta:

- `phpPass`: `3008 -> 3009`
- `benchmarkDenominator.mapped`: `3161 -> 3162`
- `mappedBibtexCslProcessorEventMetadataCases`: `1`
- `bibtexCslProcessorEventMetadataAssertions`: `12`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP BibTeX
parser, CSL item normalization, date parser, name parser, and bibliography
renderer. It does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography
managers, Cabal/Haskell runners, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Non-Overlap

This does not repeat accepted rich `CitationCslProcessor` event handling,
legacy short creator, entry-alias, extended creator-role, original-publication,
authority-identifier, or eprint/access metadata slices. It is limited to legacy
BibLaTeX event metadata aliases in the compact BibTeX/CSL handoff.
