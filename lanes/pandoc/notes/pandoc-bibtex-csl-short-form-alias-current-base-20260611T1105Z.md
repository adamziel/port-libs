# Pandoc BibTeX/CSL Short Form Alias Slice

Date: 2026-06-11 UTC
Base after rebase: 407d7449945672e0605a25fb4a4b5888a14c2249
Micro-slice: plib-zii06 / pandoc-bibtex-csl-short-form-alias-current-base-20260611T1105Z

## Scope

This slice maps CSL-shaped short-form title aliases in native BibTeX input.
`short-title` and `title-short` now normalize to CSL `short-title`, while
`container-title-short` normalizes to both `container-title-short` and the
legacy `journalAbbreviation` mirror. Raw BibTeX field provenance remains
available for reviewer handoff.

The focused coverage starts from `.bib` source text, then verifies parser output,
normalized CSL item aliases, CSL short-form rendering, bibliography rendering,
and compact WordPress citation/bibliography output.

## Evidence

Red probe before the parser patch:

```text
CitationCslProcessor::bibtexItems() left short-title, title-short, and container-title-short empty; collection-title-short already mapped.
```

Final focused verification:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-short-form-alias-handoff.php
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4522 assertions, 0 failures
php lanes/pandoc/examples/wordpress-bibtex-csl-short-form-alias-handoff.php --self-test
wordpress-bibtex-csl-short-form-alias-handoff self-test passed
```

Full lane verification:

```text
php tools/run-tests.php lanes/pandoc/tests
44 test files, 62541 assertions, 0 failures
```

Bookkeeping delta:

- `phpPass`: `3049 -> 3050`
- `benchmarkDenominator.mapped`: `3185 -> 3186`
- `inventory.mappedBibtexCslCoreCases`: `10 -> 11`
- `inventory.bibtexCslCoreAssertions`: `161 -> 184`
- `mappedBibtexCslShortFormAliasCases`: `1`
- `bibtexCslShortFormAliasAssertions`: `23`

## Dependency Closure

No new support component is needed. The slice reuses the native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, Cabal/Haskell runner,
browser renderer, external validator, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted CSL short-form rendering, BibLaTeX journal
abbreviation, collection-title-short, short creator, event metadata, original
publication, printing/supplement number, or authority fallback slices. It is
limited to importing CSL-shaped title and container short-form aliases from
BibTeX source text into the already-supported CSL/WordPress rendering path.
