# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T004014Z`

Accepted base: `7dc69d5aea3948399682b3467340c79f130a10f6`

## Behavior

- Extended bounded native `CslStyle` parsing to preserve CSL `citation` and
  `bibliography` `<sort><key>` elements.
- Supports variable sort keys plus bounded macro-key aliases for author/name,
  date/year/issued, and title-shaped macro names.
- Validates sort keys early: each key must declare exactly one `variable` or
  `macro`, `sort` must be `ascending` or `descending`, and empty sort blocks
  are rejected before citation processing.
- `CitationCslProcessor` now applies style-declared citation sort keys when
  rendering citation clusters, while preserving source order when no sort is
  declared.
- Appended CSL bibliography blocks now apply style-declared bibliography sort
  keys over normalized author/editor names, issued dates, titles, container
  titles, publishers, types, ids, and stable first-cited order for ties.
- Updated the WordPress citation handoff smoke so reviewer-facing Works Cited
  output proves sorted references without invoking citeproc, Pandoc, BibTeX,
  Biber, bibliography managers, online services, or Haskell runners.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated
  worktree; no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL 1.0.2 style model already
  used by the lane: independent styles carry `citation` and optional
  `bibliography` elements, those elements may carry `sort` blocks, and sort
  keys declare variables or macros with ascending/descending order.
- This does not attempt full CSL macro rendering, name rendering options,
  locale date formatting, citation-position logic, disambiguation, note-style
  citeproc output, external style catalogs, or full citeproc parity.

## Evidence

- Baseline before adding this test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 212 assertions, 0 failures`.
- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 214 assertions, 1 failures`; the missing behavior
  was `citationSort` in `CitationCslProcessor::cslStyleSummary()`.
- `php -l lanes/pandoc/src/CslStyle.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 232 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  19 selected test files, 4,756 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style layout localization, bibliography layout affixes,
BibTeX/BibLaTeX parsing, BibTeX crossref inheritance, bracketed citation
cluster parsing, missing citation preservation, DOCX/ODT/EPUB package parsing,
table geometry, ZIP/OPC package primitives, doctemplate, YAML, archive
compression, math/TeX, legacy DOC/CFB, charset helpers, PDF handoff planning,
or upstream-runner dependency audit work.

## Dependency Closure

No external dependency is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, Markdown reader/writer, and WordPress block
writer. Remaining citation closure is bounded follow-up work: broader
BibLaTeX entry families, richer TeX accent/control decoding, CSL macro/text/
date/name rendering beyond bounded layout/terms/sort keys, citation-position
logic, bibliography disambiguation, note-style output, external style
catalogs, and full upstream runner hydration.
