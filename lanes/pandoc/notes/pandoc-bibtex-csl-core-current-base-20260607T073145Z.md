# Pandoc BibTeX/CSL Current-Base URL Description Label Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T073145Z`

Base accepted HEAD: `f86b341055369dfc4d6c13b1414155be4d2d54db`

## Behavior

- Added bounded native BibLaTeX URL-description label handoff.
- `BibtexCslParser` now maps `urldescription`, `urltitle`, `urllabel`, `url-label`, and `url-description` into CSL-like `URL-label` metadata.
- `CitationCslProcessor` now normalizes direct `URLLabel`/`url-label` items into `urlLabel`, preserves URL label text in default bibliography entries, and exposes `url-label`, `url-description`, `urldescription`, `urltitle`, and `urllabel` as bounded CSL text variables.
- `wordpress-bibtex-csl-handoff.php` now includes a source with `urldescription` and verifies normalized metadata plus WordPress bibliography output.

Source-truth boundary: BibLaTeX-style URL description/title fields are treated as reviewer-facing URL label metadata for import handoff. This is native support-library behavior only; it does not execute or claim full citeproc, BibTeX, Biber, bibliography-manager, or upstream Pandoc runner parity.

## Focused Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex url description labels into csl review metadata
Expected: 'Reviewer mirror copy'
Actual: NULL
1 test files, 1904 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1917 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

## Status Delta

- `benchmarkDenominator.mapped`: `1884 -> 1885`.
- `mappedBibtexCslCoreCases`: `5 -> 6`.
- `bibtexCslCoreAssertions`: `80 -> 95`.
- `phpPass`: `1467 -> 1468`.
- Focused `CitationCslProcessorTest.php`: `1902 -> 1917` assertions (`+15`) with one new focused PASS case.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `WordPressBlockWriter`, the existing WordPress BibTeX/CSL handoff example, and focused PHP tests.

No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This slice deliberately avoids recent BibTeX/CSL work for entry subtype, library call-number, pagination/bookpagination, article-number/eid, event-place lists, original-language lists, related/xref records, identifiers, sort overrides, custom/user/verbatim fields, reprint/review metadata, and original-title addendum handoff. It only owns URL description/title label metadata and CSL variable handoff.

## Follow-up

Keep future BibTeX/CSL work bounded to non-overlapping safe BibLaTeX datamodel aliases, name-list annotations beyond existing `+an` summaries, or CSL variable handoff gaps with focused PHP tests.
