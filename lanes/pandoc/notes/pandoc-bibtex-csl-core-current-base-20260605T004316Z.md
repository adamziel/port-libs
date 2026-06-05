# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T004316Z`

Accepted base: `adff3ff4be5f1e1b07ff473e9aa513203236699c`

## Behavior

- Added bounded BibLaTeX `@xdata` inheritance before CSL item normalization.
- `@xdata` entries are now data-only sources and are not emitted as bibliography
  items.
- Cited entries inherit missing fields from comma-separated xdata packets while
  preserving child-owned fields and rejecting xdata cycles deterministically.
- Added bounded `@inreference` mapping to CSL `entry-encyclopedia`.
- Preserves inherited reviewer metadata on CSL items: `langid`/`language`,
  `abstract`, `keywords`, and BibLaTeX/JabRef-style `file` entries parsed as
  source-file records.
- Updated the WordPress BibTeX handoff smoke so an xdata-backed glossary entry
  renders normally while source language, keyword, and file metadata remain
  available for import review.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
  online service, or upstream Haskell runner is invoked.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 213 assertions, 1 failures`.
  - Failure: the new xdata case expected one emitted CSL item, but the parser
    emitted three because `@xdata` packets were treated as bibliography items.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 237 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`:
    `19 test files, 4785 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - `php -l lanes/pandoc/src/BibtexCslParser.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`:
    no syntax errors.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 11 focused cases and 212 assertions
  to 12 focused cases and 237 assertions.
- Lane status moves from 475 to 476 PHP PASS cases.
- Manifest mapped checks move from 948 to 949.
- BibTeX/CSL mapped core cases move from 2 to 3, and BibTeX/CSL assertions move
  from 38 to 63.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref inheritance,
common TeX accent decoding, CSL style XML/locales, simple author-date citation
rendering, bracketed citation cluster parsing, missing citation preservation,
EPUB3 package handoff, DOCX/ODT package parsing, table geometry, ZIP/OPC
package primitives, doctemplate, YAML, archive compression, math/TeX, legacy
DOC/CFB, charset helpers, PDF handoff planning, XML/HTML5 DOM, or
upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and
WordPress block writer. Remaining bounded citation follow-up work includes
BibLaTeX entry sets and related entries, richer attachment/source-file policy,
full CSL macro/text/date/name rendering, bibliography sorting, disambiguation,
citation-position logic, note-style output, and full upstream runner hydration.
