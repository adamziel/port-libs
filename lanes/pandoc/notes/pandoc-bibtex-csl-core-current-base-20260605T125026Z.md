# Pandoc BibTeX/CSL Core Current Base Split URL Date

Slice: `pandoc-bibtex-csl-core-current-base-20260605T125026Z`
Base accepted HEAD: `af2575a57bdb5d0f0b53fdb98a89256019c109bb`

## Scope

Implemented a bounded BibLaTeX-to-CSL access-date handoff for split URL date
fields. `BibtexCslParser` now maps `urlyear`, `urlmonth`, and `urlday` into
CSL `accessed` date-parts when no whole `urldate`, `accessed`, or `accessdate`
field is present. Whole URL date fields keep precedence over component fields.

The focused test covers:

- month-name and numeric `urlmonth` component parsing;
- whole `urldate` precedence over split `urlyear`/`urlmonth`/`urlday`;
- normalized `accessedDate` metadata in `CitationCslProcessor`;
- bounded CSL `<date variable="accessed">` rendering;
- WordPress bibliography block output with the accessed date visible.

The WordPress example now includes `@split-url-date` and verifies the normalized
and raw CSL metadata in `--self-test`.

## Red/Green Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1097 assertions, 0 failures
```

During development, the new focused test first failed on an expected raw
`urlmonth` value (`jun` vs the existing parser-normalized `June`), confirming
the new assertion path was active. The expectation was corrected to match the
existing BibTeX string macro normalization behavior.

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1115 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Status delta:

- `lanes/pandoc/lane-status.json` `phpPass`: `903 -> 904`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped: `1361 -> 1362`
- `mappedBibtexCslCoreCases`: `2 -> 3`
- `bibtexCslCoreAssertions`: `38 -> 56`
- Focused assertion delta: `+18`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, bounded CSL style renderer,
`MarkdownReader`, and `WordPressBlockWriter` support rows.

No Pandoc, citeproc, BibTeX, Biber, Cabal build, Haskell runner, external
bibliography manager, online service, Word, LibreOffice, zip/unzip, or PDF/TeX
engine was executed.

## Non-Overlap

This does not repeat the accepted CSL date-range/date-part style rendering
slices or the existing BibTeX/BibLaTeX coverage for crossref/xdata inheritance,
source-file diagnostics, entry sets, related entries, translation/original
publication metadata, legal/patent metadata, title/subtitle/addon metadata,
publication details, publisher/location literal lists, journal abbreviations,
page-first metadata, multivolume metadata, note/addendum/howpublished,
entry-subtype review metadata, editorial roles, name annotations, shorthand
labels, software/dataset metadata, event metadata, event organizers, aliases,
or ZIP/OPC/EPUB/DOCX/PDF support-library slices.

Remaining full parity work stays bounded for later slices: full citeproc
disambiguation, note-style output, richer locale terms, broader BibLaTeX data
model behavior, bibliography-manager-specific import quirks, and upstream
Pandoc Haskell runner dependency hydration.
