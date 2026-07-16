# Pandoc Citation/CSL Current-Base Year-Suffix Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260605T075332Z`
Base: `1b72408ed94109ba862fc9360cd5e316e7f53484`

## Behavior

- Added bounded CSL `disambiguate-add-year-suffix` parsing on `<citation>`.
- Annotates known ambiguous same-creator/same-issued-year citations with stable `a`, `b`, ... suffixes.
- Exposes `year-suffix` to CSL text rendering so styles can decide where the suffix appears.
- Carries suffixes into fallback author-date citation labels, bibliography term labels, custom bibliography entries, and WordPress definition-list output.
- Rejects non-boolean `disambiguate-add-year-suffix` values with a citation-option diagnostic.

## Evidence

Red-first check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 800 assertions, 1 failures
FAIL applies bounded csl year suffix disambiguation for ambiguous author dates
Expected: true
Actual: NULL
```

Passing focused checks after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 824 assertions, 0 failures
```

```text
php lanes/pandoc/examples/wordpress-citation-csl-year-suffix-handoff.php --self-test
wordpress-citation-csl-year-suffix-handoff self-test passed
```

Focused assertion movement: `798 -> 824` in `CitationCslProcessorTest.php`.
Lane pass movement: `phpPass 758 -> 759`.
Mapped denominator movement: `1217 -> 1218`; `mappedCitationCslCoreCases 10 -> 11`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP CSL style parser, citation AST annotation path, bibliography handoff, Markdown reader/writer, and WordPress block writer. No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, Word, LibreOffice, online service, browser renderer, external template engine, or TeX/PDF engine was executed.

## Non-Overlap

This slice does not repeat the accepted Citation/CSL date-part, text-case, quote/strip-periods, macro, choose, locator/label, number, citation-position, name-part, or bibliography display-part work. Remaining CSL follow-ups include bibliography id generation, collapse, note-style output, near-note behavior, punctuation-in-quote, and broader citeproc parity.
