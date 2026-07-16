# Pandoc BibTeX/CSL Event Place List Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260606T101552Z`
Base: `f2b77d802e93bb0b73e3302173738b4dc3701217`

## Scope

Added bounded native PHP BibLaTeX event-place list handoff support.
`BibtexCslParser` now treats `eventvenue`, `eventlocation`, `eventplace`, and
`venue` as BibLaTeX literal-list fields for CSL `event-place` metadata while
also preserving an `event-place-list` review array. Crossref inheritance carries
that list from proceedings records to child conference papers.

`CitationCslProcessor` now normalizes direct CSL `event-place-list` items,
derives scalar `eventPlace` values for `<text variable="event-place"/>`, exposes
`<text variable="event-place-list"/>`, and uses the plural event-place term in
fallback bibliography output when multiple venues are present.

## Evidence

Baseline focused citation coverage before the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1608 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1625 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-event-place-list-handoff.php --self-test
wordpress-bibtex-csl-event-place-list-handoff self-test passed
```

Syntax checks:

```text
php -l lanes/pandoc/src/BibtexCslParser.php
php -l lanes/pandoc/src/CitationCslProcessor.php
php -l lanes/pandoc/tests/CitationCslProcessorTest.php
php -l lanes/pandoc/examples/wordpress-bibtex-csl-event-place-list-handoff.php
```

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1294 -> 1295`.
- Manifest mapped denominator: `1708 -> 1709`.
- `mappedBibtexCslCoreCases`: `3 -> 4`.
- `bibtexCslCoreAssertions`: `52 -> 69`.
- Focused citation coverage: `+1` PASS case and `+17` assertions.

## Non-Overlap

This does not repeat accepted BibTeX/CSL event title/type/date scalar metadata,
event organizer names, localized event labels, publisher/location lists,
journal abbreviations, article numbers, pagination, call numbers, entry
subtypes, PubMed identifiers, reviewed-work metadata, reprint titles, broader
CSL style disambiguation, or full citeproc parity. It is limited to distributed
event-place list preservation for the existing native BibLaTeX/CSL handoff.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and
`WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal
solver/build/test command, Haskell runner, external bibliography manager,
online sanitizer, online service, live provider test, or live-service provider
test was executed.

Full upstream Pandoc/citeproc runner parity remains gated on hydrating the
pinned Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
Cabal project/package files and runner dependency closure.
