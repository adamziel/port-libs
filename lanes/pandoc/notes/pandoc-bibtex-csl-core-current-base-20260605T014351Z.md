# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260605T014351Z`

Accepted base: `941dc5e12dd1faec095f473383de971e06400330`

## Behavior

- Added bounded BibLaTeX translation and original-publication metadata handoff.
- `BibtexCslParser` now maps `translator`, `origtitle`, `origdate`,
  `origpublisher`, `origlocation`/`origaddress`, and `origlanguage` into
  CSL-style item fields.
- `CitationCslProcessor` now normalizes translator lists plus original title,
  date, publisher, place, and language metadata and renders that metadata in
  bounded bibliography review output.
- Updated the WordPress BibTeX handoff example so translated sources preserve
  original publication audit metadata in rendered WordPress blocks.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external renderer,
  online service, or upstream Haskell runner was invoked.

## Source Truth

- This slice follows the accepted lane's existing BibLaTeX/CSL handoff model:
  BibLaTeX entry fields are normalized into CSL JSON-compatible item metadata
  before local CSL processing.
- The field family is bounded to translator and original-publication metadata
  used by translated source packets; it does not attempt full citeproc macro or
  style rendering.

## Red/Green Evidence

- Baseline command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before test edit: `1 test files, 302 assertions, 0 failures`.
- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 306 assertions, 1 failures`.
  - Failure: the new translation metadata case expected `original-title`
    metadata from `origtitle`, but the parser emitted `NULL`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 323 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`:
    `19 test files, 5345 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 15 focused cases and 302
  assertions to 16 focused cases and 323 assertions.
- Lane status moves from 508 to 509 PHP PASS cases.
- Manifest mapped checks move from 983 to 984.
- BibTeX/CSL mapped core cases move to 5, and BibTeX/CSL assertions move to
  114 after carrying forward the previously accepted xdata, crossref,
  TeX-accent, entry-set, and related metadata slices plus this translation
  metadata slice.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, BibTeX crossref
inheritance, common TeX accent decoding, xdata inheritance, BibLaTeX entry
sets, related-entry metadata, CSL style XML/locales, citation cluster parsing,
missing citation preservation, EPUB3 package handoff, DOCX/ODT package
parsing, table geometry, ZIP/OPC package primitives, doctemplate, YAML,
archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF handoff
planning, XML/HTML5 DOM, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and
WordPress block writer. Remaining bounded citation follow-up work includes
richer BibLaTeX entry families beyond crossref/xdata/sets/related/translation
metadata, attachment source-file policy, full CSL macro/text/date/name
rendering, bibliography disambiguation, citation-position logic, note-style
output, and full upstream runner hydration.
