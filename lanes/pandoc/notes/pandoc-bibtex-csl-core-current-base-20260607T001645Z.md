# Pandoc BibTeX/CSL Open-Ended Date Slice

- Session: `port-dev-pandoc-bibtex-csl-20260607T001645Z`
- Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T001645Z`
- Accepted base: `13afa6bbcfe66cce46d4907c863b6703a36c5f2e`
- Lane: `pandoc`

## Behavior

Implemented bounded native PHP BibLaTeX open-ended date interval preservation for CSL handoff:

- `BibtexCslParser` now maps single-ended intervals such as `2020/`, `/2024`, and `2026-06-01/` into CSL `date-parts` plus `open-ended` boundary metadata instead of treating them as literals.
- `CitationCslProcessor` normalizes `open-ended` / `openEnded` CSL date metadata into `openEnded` display state for issued, accessed, original, and event dates.
- Default author-date labels, bibliography entries, custom CSL `<date>` elements, and selected `<date-part>` rendering keep the leading or trailing interval slash visible.
- The WordPress BibTeX/CSL handoff smoke now includes an open-ended source and verifies issued, original, and accessed date interval metadata.

## Non-Overlap

This does not repeat accepted bounded closed date-range handling, date-time parts, uncertain/approximate date markers, split URL dates, CSL date-part forms, entry subtype, call-number/library, pagination/bookpagination, article-number/eid, event-place lists, related/xref entries, or PDF/office/package support work. It only fills the previously noted open-ended BibLaTeX date interval gap.

## Dependency Closure

No new support component is required. The slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online sanitizer, online service, live provider test, or live-service provider test was executed.

## Evidence

Red-first focused run after adding the test failed on the missing open interval metadata:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL maps open ended biblatex date ranges into csl date metadata
1 test files, 1804 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS maps open ended biblatex date ranges into csl date metadata
1 test files, 1826 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Expected movement:

- Manifest mapped checks: `1835 -> 1836`
- `mappedBibtexCslCoreCases`: `4 -> 5`
- `bibtexCslCoreAssertions`: `65 -> 87`
- Focused assertion growth: `+22` assertions in `CitationCslProcessorTest.php`

## Follow-Up

Keep BibLaTeX seasons, eras/BCE dates, richer EDTF uncertainty beyond `?`, `~`, `%`, and broader citeproc style date rendering parity as separate bounded Citation/CSL slices.
