# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T011132Z`

Accepted base: `24d8a02fe41aad85f9cde8b9bb0e256f650c48c8`

## Behavior

- Added bounded BibLaTeX static entry-set handoff support for `@set` entries
  with `entryset = {key1,key2,...}`.
- Entries with `options = {dataonly}` are preserved for set/related summaries
  but are no longer emitted as standalone CSL bibliography items.
- `@set` CSL items now carry reviewer metadata: `entrySet`,
  `entrySetItems`, and `missingEntrySetKeys`.
- Entries with BibLaTeX `related`, `relatedtype`, and `relatedstring` fields
  now carry `relatedKeys`, `relatedItems`, `missingRelatedKeys`,
  `relatedType`, and `relatedString` metadata for reviewer audit packets.
- Set and related summaries reuse the existing BibTeX/BibLaTeX field
  normalization, including crossref inheritance, CSL type mapping, dates,
  pages, names, URLs, and data-only markers.
- Updated the WordPress BibTeX handoff example so a cited entry set and a
  related manual render normally while entry-set members and missing related
  keys remain inspectable through native PHP metadata.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
  online service, or upstream Haskell runner was invoked.

## Source Truth

- The CTAN BibLaTeX manual describes static entry sets as `@set` entries whose
  `entryset` field lists member entry keys, and describes `related`,
  `relatedtype`, and `relatedstring` as entry fields for related-entry
  relationships.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 258 assertions, 1 failures`.
  - Failure: the new entry-set case expected two emitted CSL items, but the
    parser emitted five because `options={dataonly}` members were treated as
    standalone bibliography items and no set/related metadata was attached.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 287 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`:
    `19 test files, 5070 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 14 focused cases and 257 assertions
  to 15 focused cases and 287 assertions.
- Lane status moves from 491 to 492 PHP PASS cases.
- Manifest mapped checks move from 964 to 965.
- BibTeX/CSL mapped core cases move to 4, and BibTeX/CSL assertions move to 93
  after carrying forward the previously accepted xdata metadata slice and this
  entry-set/related metadata slice.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref inheritance,
common TeX accent decoding, xdata inheritance, CSL style XML/locales, citation
cluster parsing, missing citation preservation, EPUB3 package handoff,
DOCX/ODT package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, XML/HTML5 DOM, or upstream-runner dependency
audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and
WordPress block writer. Remaining bounded citation follow-up work includes
richer attachment/source-file policy, full CSL macro/text/date/name rendering,
bibliography disambiguation, citation-position logic, note-style output, and
full upstream runner hydration.
