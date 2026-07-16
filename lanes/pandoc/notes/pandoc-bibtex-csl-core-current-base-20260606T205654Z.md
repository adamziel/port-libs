# Pandoc BibTeX/CSL Original Title Metadata Slice

Date: 2026-06-06 UTC
Base: 707b60a141f4e8a970f90fe5df3b1c2d5991fbaa
Micro-slice: pandoc-bibtex-csl-core-current-base-20260606T205654Z

## Scope

This slice maps one bounded BibLaTeX original-title metadata cluster without invoking Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.

The implementation reuses the existing native PHP BibTeX parser and CSL renderer:

- `origtitle` plus `origsubtitle` now compose into CSL `original-title`.
- `origtitleaddon` is preserved as CSL `original-title-addon`.
- `CitationCslProcessor` normalizes the addendum for parsed BibLaTeX and direct CSL item input.
- Default bibliography output and CSL `<text variable="original-title-addon"/>` rendering expose the addendum.
- The WordPress BibTeX/CSL handoff smoke includes an original-subtitle source packet.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1789 assertions, 1 failures
```

The failing assertion showed `original-title` as `Manual de Migración` before the parser composed `origsubtitle`.

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1803 assertions, 0 failures

php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

Bookkeeping delta:

- `phpPass`: 1400 -> 1401
- mapped denominator: 1813 -> 1814
- `mappedBibtexCslCoreCases`: 4 -> 5
- `bibtexCslCoreAssertions`: 65 -> 81

## Non-Overlap

This does not repeat the accepted BibTeX/CSL entry-subtype, library call-number, pagination/bookpagination, article-number/eid, event-place literal-list, reviewed-work metadata, reprint-title, date marker/time-part, xref, or custom-field slices. It is limited to `origsubtitle` and `origtitleaddon` original-title metadata handoff.

## Dependency Closure

No new support component is needed. The slice reuses native PHP BibTeX parsing, CSL item normalization/rendering, MarkdownReader citation parsing, and WordPressBlockWriter bibliography output. Full Pandoc runner parity, citeproc parity, BibTeX/Biber execution, broader BibLaTeX inheritance, CSL locale/style completeness, citation-position disambiguation, and note-style output remain separate bounded follow-up work.
