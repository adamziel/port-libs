# Pandoc BibTeX/CSL Current-Base Primary Language List Slice

Micro-slice: `pandoc-bibtex-csl-core-current-base-20260607T110042Z`
Base accepted HEAD: `9796c261eb2e505bb956aa1c10e0f50625834924`

## Behavior

- Added bounded native BibLaTeX primary `language` literal-list handoff.
- `BibtexCslParser` now splits `language = {english and french and spanish}` into structured CSL-like `language-list` metadata while keeping scalar `language` as `english; french; spanish`.
- `langid` and `hyphenation` remain scalar fallback language metadata when no primary `language` list is present.
- `CitationCslProcessor` now normalizes direct CSL-like `language-list` input, derives scalar `language` display text when needed, and exposes `language-list` to bounded CSL `<text variable="language-list"/>` rendering.
- `wordpress-bibtex-csl-handoff.php` now includes a primary language-list source and verifies both normalized metadata and custom WordPress CSL rendering.

Source-truth boundary: BibLaTeX treats `language` as imported source-language metadata that can be a literal list. This slice preserves the list for native review handoff only; it does not execute or claim full citeproc, BibTeX, Biber, bibliography-manager, or upstream Pandoc runner parity.

## Focused Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex primary language lists into csl review metadata
Expected: 'english; french; spanish'
Actual: 'english and french and spanish'
1 test files, 1929 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 1944 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test
wordpress-bibtex-csl-handoff self-test passed
```

## Status Delta

- `benchmarkDenominator.mapped`: `1908 -> 1909`.
- `mappedBibtexCslCoreCases`: `5 -> 6`.
- `bibtexCslCoreAssertions`: `80 -> 95`.
- `phpPass`: `1489 -> 1490`.
- Focused `CitationCslProcessorTest.php`: `+1` PHP PASS case / `+15` focused assertions from the red-first failure to final green run.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, `WordPressBlockWriter`, the existing WordPress BibTeX/CSL handoff example, and focused PHP tests.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice deliberately avoids recent BibTeX/CSL work for original-language lists, URL description labels, event-place lists, entry subtype, library call-number, pagination/bookpagination, article-number/eid, PubMed/media identifiers, related/xref records, sort overrides, custom/user/verbatim fields, reviewed/reprint metadata, and `and others` name-list sentinels. It only owns primary `language` literal-list metadata and CSL variable handoff.

## Follow-Up

Keep future BibTeX/CSL work bounded to non-overlapping safe BibLaTeX datamodel aliases, name-list annotation metadata beyond existing `+an` summaries, or CSL variable handoff gaps with focused PHP tests.
