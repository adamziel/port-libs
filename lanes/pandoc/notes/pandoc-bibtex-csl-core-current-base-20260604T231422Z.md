# Pandoc BibTeX CSL Core Current Base

Slice: `pandoc-bibtex-csl-core-current-base-20260604T231422Z`

Accepted base: `fd0f5327abfd3b58715219a1c13c4c8295941253`

## Behavior

- Added bounded BibTeX crossref inheritance before CSL item normalization.
- The native parser now indexes parsed bibliography entries by citation key,
  resolves crossrefs after the full `.bib` packet is parsed, and then emits
  CSL-like items through the existing `CitationCslProcessor` path.
- Child `article`, `inproceedings`, `incollection`, `inbook`, and `conference`
  entries inherit missing parent metadata while preserving child fields.
- Proceedings and collection parent `title` values are inherited as the child
  CSL container title, not as the child item title. Parent editor, publisher,
  date, and page-relevant metadata are inherited only when the child field is
  absent.
- Duplicate entry keys and crossref cycles now fail deterministically without
  invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, or online
  services.
- Updated the WordPress BibTeX handoff smoke so a cited proceedings child entry
  produces reviewer-visible citation and bibliography blocks with inherited
  conference metadata.

## Red/Green Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result before implementation: `1 test files, 148 assertions, 1 failures`.
  - Failure: the new crossref case expected `Migration Futures Conference` as
    the child `container-title`; actual value was empty.
- After implementation:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php`: no syntax
    errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
    `1 test files, 163 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`: `13 test files, 3716
    assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-handoff.php --self-test`:
    `wordpress-bibtex-csl-handoff self-test passed`.
  - JSON validation for `lanes/pandoc/lane-status.json` and
    `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: both files valid.
  - `git diff --check -- lanes/pandoc`: no whitespace errors.
  - Root harness: not run - isolated micro-slice.

## Status Delta

- `CitationCslProcessorTest.php` moves from 7 focused cases and 145 assertions
  to 8 focused cases and 163 assertions.
- Lane status moves from 389 to 390 PHP PASS cases.
- Manifest mapped checks move from 846 to 847.
- BibTeX/CSL mapped core cases move from 2 to 3, and BibTeX/CSL assertions move
  from 38 to 56.

## Non-Overlap

This does not repeat accepted CSL JSON item basics, source-access date/name
metadata, initial BibTeX/BibLaTeX parser coverage, simple author-date citation
rendering, bracketed citation cluster parsing, missing citation preservation,
legacy DOC/CFB FIB preflight, EPUB3 package handoff, DOCX/ODT package parsing,
table geometry, ZIP/OPC package primitives, doctemplate, YAML, archive
compression, math/TeX, charset helpers, PDF handoff planning, or upstream-runner
dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, Markdown reader/writer, and WordPress
block writer. Remaining bounded citation follow-up work is broader BibLaTeX
entry families, TeX accent decoding, CSL style XML/locales, citation-position
disambiguation, note-style output, and full upstream runner hydration.
