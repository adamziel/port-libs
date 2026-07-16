# Pandoc BibTeX/CSL Archive Collection Handoff

Slice: `pandoc-bibtex-csl-core-current-base-duplicate-20260609T083513Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

`BibtexCslParser` now maps bounded archive collection fields from `.bib`
source records into CSL review metadata:

- `archivecollection`
- `archive-collection`
- `archive_collection`

The parsed CSL item now exposes `archive-collection`, and its
`archive-summary` includes the collection segment between archive name and
archive location. Existing direct CSL item handling for archive collection
metadata was already present; this slice closes the missing BibTeX-source
import path.

## Evidence

Baseline focused command before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4004 assertions, 0 failures
```

Final focused command after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4023 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-archive-collection-handoff.php --self-test
wordpress-bibtex-csl-archive-collection-handoff self-test passed
```

Delta:

- `phpPass`: `2530 -> 2531`
- `benchmarkDenominator.mapped`: `2898 -> 2899`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 140`
- Focused assertions: `4004 -> 4023`

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct CSL archive collection alias slice,
which starts from PHP item arrays. It does not repeat accepted BibTeX/CSL
archive-summary/eprint metadata, source-file attachment policy, event place,
source locator, call-number, rights/license, related-entry, entry-set,
identifier, volume/title, entry-type, or creator-role slices. The owned change
is only `.bib` archive collection field import into already-supported CSL
archive metadata.

## Dependency Closure

No new native support component is needed. This reuses the native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`WordPressBlockWriter`, and focused PHP test runner.

No Pandoc, BibTeX, Biber, citeproc, Cabal/Haskell runner, Word, LibreOffice,
zip/unzip, external template engine, TeX/PDF engine, browser renderer, online
service, live provider test, or live-service provider test was executed.

## Follow-Up

A future non-overlapping BibTeX/CSL slice can target another remaining
BibLaTeX datamodel alias or CSL variable gap. No follow-up is required before
accepting this patch.
