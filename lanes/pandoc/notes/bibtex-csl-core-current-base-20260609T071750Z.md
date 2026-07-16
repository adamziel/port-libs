# BibTeX/CSL Core Current-Base Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T071750Z`
Base: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Source Truth

The lane already maps BibTeX/BibLaTeX access dates into CSL `accessed` metadata through `urldate`, `accessed`, `accessdate`, and split `urlyear`/`urlmonth`/`urlday` fields. Legacy imported `.bib` files often use equivalent scalar access-date aliases such as `lastchecked`, `lastaccessed`, and `visited`; this slice preserves those values through the same bounded CSL `accessed` date path and raw BibTeX provenance.

No hydrated Pandoc upstream checkout was present in `.upstream-cache/pandoc` for this isolated worker, so this handoff uses the lane's existing BibTeX/CSL parser and CSL accessed-date contract as the bounded source of truth.

## Implemented Behavior

- `BibtexCslParser` now maps scalar `lastchecked`, `lastaccessed`, and `visited` fields into CSL `accessed` metadata.
- The existing date parser handles exact dates, uncertain markers such as `2026-06?`, and literal review text without adding a new support component.
- Raw provenance remains visible in `rawBibtex.fields`.
- WordPress citation and bibliography handoff output keeps exact, uncertain, and literal imported access-date metadata visible for review.

## Evidence

Baseline focused verification before this patch:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3933 assertions, 0 failures`.

Red check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3935 assertions, 1 failures`; the new focused case failed because `lastchecked` produced no CSL `accessed` date.

Focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3954 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-bibtex-csl-access-date-alias-handoff.php --self-test`

Result: `wordpress-bibtex-csl-access-date-alias-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `BibtexCslParser` date parser, `CitationCslProcessor` accessed-date normalization, `MarkdownReader`, and `WordPressBlockWriter` paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, live-service provider test, BibTeX, Biber, or citeproc process was executed.

## Non-Overlap

This handoff avoids accepted BibTeX/CSL clusters for `@misc` document type routing, source/section/supplement variables, periodical/suppperiodical type mapping, letter personal-communication mapping, manual/booklet type mapping, media type aliases, source attachments, entry sets, related entries, rights, language, original publication metadata, date ranges, available/submitted dates, split URL-date fields, and Citation/CSL names substitute behavior. It owns only bounded legacy scalar access-date aliases into CSL `accessed` metadata.
