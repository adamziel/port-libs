# Pandoc BibTeX/CSL Reprint Title Slice

Slice: `pandoc-bibtex-csl-core-current-base-20260606T080438Z`
Base: `abd9b455aa8ac5c4b63d3568f04bfd5d77b5e0b4`

## Scope

Added a bounded native PHP BibLaTeX handoff for `reprinttitle` / `reprint-title`.
The parser now exposes CSL-shaped `reprint-title`, the processor normalizes it
as `reprintTitle`, default review bibliography output includes `Reprint title`,
and bounded CSL styles can render `<text variable="reprint-title"/>`.

This follows the BibLaTeX source-truth shape where `reprinttitle` is source
metadata for the title of a reprint. The slice intentionally keeps full
BibLaTeX related-entry logic, citeproc parity, and style-localized formatting
out of scope.

## Evidence

- Baseline before the new assertion:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1585 assertions, 0 failures`.
- Red-first after adding the focused case and before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 1587 assertions, 1 failures`; parsed
  `reprint-title` metadata was `NULL`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1599 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-reprint-title-handoff.php --self-test`
  passed.

Status delta: `phpPass` 1250 -> 1251, mapped denominator 1694 -> 1695,
`mappedBibtexCslCoreCases` 2 -> 3, `bibtexCslCoreAssertions` 38 -> 52.

## Dependency Closure

No new support component is needed. This reuses the existing native
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter` paths. No Pandoc, Cabal, Haskell runner, citeproc,
BibTeX, Biber, external bibliography manager, online sanitizer, online service,
or live provider test was executed.

## Non-Overlap

This does not repeat the accepted BibTeX/CSL entry-subtype, library
call-number, pagination/bookpagination, article-number/eid, PubMed identifier,
or reviewed-title review-metadata slices. Follow-up remains broader BibLaTeX
related-entry handling, original/reprint relationship rendering, CSL locales,
style disambiguation, note-style output, and full citeproc parity.
