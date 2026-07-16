# BibTeX/CSL Core Current-Base Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260609T070257Z`
Base: `53cc273b044292e061f08ae6f6fdabc37210dcb0`

## Source Truth

Pandoc/Citeproc routes generic BibTeX `@misc` records through a generic CSL item shape rather than leaving `misc` as a CSL item type. CSL exposes `document` as the bounded generic document type usable by type conditionals, while the lane still preserves raw BibTeX provenance for reviewer audit output.

No hydrated Pandoc upstream checkout was present in `.upstream-cache/pandoc` for this isolated worker, so this handoff uses the lane's existing BibTeX/CSL parser and CSL type-conditional contract as the bounded source of truth.

## Implemented Behavior

- `BibtexCslParser` now maps BibTeX/BibLaTeX `@misc` entries to CSL `document`.
- Raw provenance remains visible as `rawBibtex.type = misc`.
- CSL style XML `<if type="document">` branches now match imported `@misc` records.
- WordPress citation and bibliography handoff output keeps `howpublished`, `note`, and URL metadata visible for generic source packets.

## Evidence

Baseline focused verification before this patch:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3901 assertions, 0 failures`.

Red check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3904 assertions, 1 failures`; the new focused case failed because `@misc` still produced CSL type `misc` instead of `document`.

Focused verification after implementation:

`php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`

Result: `1 test files, 3919 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-bibtex-csl-misc-document-type-handoff.php --self-test`

Result: `wordpress-bibtex-csl-misc-document-type-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `BibtexCslParser`, `CitationCslProcessor`, `CslStyle`, `MarkdownReader`, and `WordPressBlockWriter` paths.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external template engine, external converter, TeX/PDF engine, browser renderer, online service, live provider test, live-service provider test, BibTeX, Biber, or citeproc process was executed.

## Non-Overlap

This handoff avoids accepted BibTeX/CSL clusters for source/section/supplement variables, periodical/suppperiodical type mapping, letter personal-communication mapping, manual/booklet type mapping, media type aliases, source attachments, entry sets, related entries, rights, language, original publication metadata, and Citation/CSL names substitute behavior. It owns only bounded `@misc` to CSL `document` type routing.
