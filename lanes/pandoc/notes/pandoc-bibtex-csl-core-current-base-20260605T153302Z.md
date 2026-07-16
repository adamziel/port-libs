# pandoc-bibtex-csl-core-current-base-20260605T153302Z

Lane: `pandoc`
Base accepted HEAD: `9f5c2e5a2a488d9988b860638e73fa38efd5184e`
Micro-slice: `pandoc-bibtex-csl-core-current-base-20260605T153302Z`

## Behavior

Bounded BibTeX/BibLaTeX handoff now maps `bookauthor` name lists into CSL
`container-author` metadata. The handoff preserves normal parsed names, literal
braced organization names, BibLaTeX `bookauthor+an` name annotations, default
WordPress bibliography review text, CSL `<names variable="container-author"/>`
rendering, and bounded style sorting by `container-author`.

This keeps imported `.bib` review packets honest when chapter-like entries have
different chapter authors and source-volume authors.

## Changes

- `src/BibtexCslParser.php`
  - Emits `container-author` CSL names from BibLaTeX `bookauthor`.
  - Reuses the existing `+an` name-annotation parser for `bookauthor+an`.
- `src/CitationCslProcessor.php`
  - Normalizes `container-author` into `containerAuthors`.
  - Exposes `container-author` to CSL names rendering, variable presence checks,
    sort keys, name-annotation summaries, and default bibliography review text.
- `tests/CitationCslProcessorTest.php`
  - Adds one focused native PHP case for parser output, annotations, literal
    container authors, default bibliography output, CSL style rendering/sorting,
    manual CSL item normalization, and WordPress block output.
- `examples/wordpress-bibtex-csl-handoff.php`
  - Adds a WordPress import smoke entry for a chapter with distinct
    `bookauthor` metadata.
- `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  - Record `phpPass` `974 -> 975`, mapped denominator `1429 -> 1430`, and the
    latest focused BibTeX/CSL slice.

## Verification

Baseline before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1245 assertions, 0 failures
```

Red-first focused test after adding the case, before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1248 assertions, 1 failures
```

The failing assertion showed missing `container-author` metadata for a
BibLaTeX `bookauthor` field.

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1265 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter` paths.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, external converter, online sanitizer, or
online service was executed.

The local upstream cache does not currently contain a hydrated Pandoc/citeproc
checkout for static source inspection. Full upstream-runner parity remains gated
on hydrating the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal package and runner files
present.

## Non-Overlap

This slice does not repeat recent BibTeX/CSL handoffs for crossref/xdata,
source-file policy, entry sets, related entries, original/translation metadata,
legal fields, date ranges, title details, publication/eprint metadata, journal
abbreviations, page-first metadata, main-title/multivolume metadata,
note/addendum/howpublished, entry subtype, editorial roles, name annotations,
shorthand labels, short creator lists, software/dataset metadata, event
metadata, event organizers, ID aliases, distributed publisher/place lists, split
URL dates, library call-number metadata, `and others` et-al sentinels, or sort
override metadata. It only owns BibLaTeX `bookauthor` to CSL
`container-author` handoff behavior.

## Follow-Up

Keep full BibTeX/BibLaTeX name-list grammar parity, richer CSL macro/date/name
rendering, localized role labels, note-style citation positions, citeproc
disambiguation, and upstream Haskell runner parity as separate bounded slices.
