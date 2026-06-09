# Pandoc BibTeX/CSL Crossref Title Parts Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T035040Z`

Base accepted HEAD: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Behavior

`BibtexCslParser` now maps bounded crossref parent title parts into child
container metadata instead of letting parent subtitles and title addenda leak
into the child item title.

- `@proceedings` / book-like parents with `title`, `subtitle`, and `titleaddon`
  now inherit into child `booktitle`, `booksubtitle`, and `booktitleaddon`.
- `@periodical` / journal-like parents now inherit into child `journal`,
  `journalsubtitle`, and `journaltitleaddon`.
- Parent `title`, `subtitle`, and `titleaddon` are removed from the inherited
  child field set after container remapping, so the child title stays its own
  source title.
- The WordPress smoke verifies both proceedings and periodical children render
  clean citation paragraphs and bibliography definitions.

## Source Truth

The bounded support-library contract is BibTeX/BibLaTeX crossref inheritance
for child container metadata. This slice keeps the native PHP handoff focused
on title-part inheritance only; it does not run Pandoc, citeproc, BibTeX,
Biber, Cabal/Haskell runners, office tools, converters, online services, or
live provider tests.

## Verification

Red-first focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded crossref parent subtitle and title addon into child containers
Expected: 'Packet Audit Trails'
Actual: 'Packet Audit Trails: Reviewer Packet Track'
1 test files, 3561 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3588 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-crossref-title-parts-handoff.php --self-test
wordpress-bibtex-csl-crossref-title-parts-handoff self-test passed
```

Focused delta:

- `phpPass`: `2254 -> 2255`
- `benchmarkDenominator.mapped`: `2660 -> 2661`
- `mappedBibtexCslCoreCases`: `7 -> 8`
- `bibtexCslCoreAssertions`: `121 -> 150`
- `CitationCslProcessorTest.php`: `3559 -> 3588` focused assertions after the
  previous accepted BibTeX/CSL slice.

Root harness was not run for this isolated micro-slice.

## Non-Overlap

This does not repeat accepted BibTeX/CSL clusters for basic crossref field
inheritance, xdata provenance, xref metadata, related entries, entry sets,
title/subtitle parsing on direct entries, original-title metadata, event-place
lists, source-file policy, creator roles, labels, pagination, identifiers, or
CSL style rendering. It only fixes crossref parent title-part remapping into
child container fields.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`BibtexCslParser` inheritance, `CitationCslProcessor` item normalization and
rendering, `MarkdownReader`, `WordPressBlockWriter`, the existing focused PHP
test runner, and the lane-local WordPress example smoke. Full upstream
Pandoc/citeproc runner parity remains a separate upstream-runner dependency
task requiring a hydrated Pandoc checkout and Haskell test executables.

## Next Task

A useful follow-up is another non-overlapping BibLaTeX datamodel alias, style
variable gap, or entry-driver metadata case with focused PHP tests.
