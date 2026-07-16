# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260603T082621Z`

Accepted base: `36c59c783187352a699b8099a3a132c271310611`

## Behavior

- Added `CitationCslProcessor` as a bounded native PHP handoff for the existing
  Pandoc citation AST nodes and CSL JSON item arrays.
- Supports fixture-backed CSL JSON item parsing, duplicate/missing/invalid item
  diagnostics, author/editor/literal-name normalization, issued year extraction,
  author-date citation cluster rendering, suppress-author and author-in-text
  modes, first-use bibliography ordering, missing citation marker preservation,
  and deterministic bibliography `definition_list` AST blocks.
- Updated Markdown and WordPress writers to render processed citation nodes via
  a `rendered` attribute while preserving existing source-text fallback for
  unprocessed citation nodes.
- Added `wordpress-citation-csl-handoff.php`, a local WordPress block smoke that
  renders citations, leaves missing citation markers visible, and emits a Works
  Cited `<dl>` without shelling out to Pandoc, citeproc, BibTeX, Biber, online
  services, or bibliography managers.

## Evidence

- `php -l lanes/pandoc/src/CitationCslProcessor.php`:
  no syntax errors.
- `php -l lanes/pandoc/src/MarkdownWriter.php`:
  no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`:
  no syntax errors.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`:
  no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 55 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  2 selected test files, 2,370 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  emitted rendered author-date citation paragraphs, visible `[@missing-source]`,
  `Works Cited`, and a CSL-derived WordPress-safe `<dl>` bibliography.

## Non-Overlap

This slice does not repeat accepted Markdown writer parenthesized link
destination behavior, table/caption/definition-list writer behavior, raw
Markdown-family blocks, HTML-reader table/list/footnote coverage, or the
existing bare citation reader boundary checks. It activates only the bounded
lane-local citation/CSL handoff needed for richer Pandoc conversion evidence.

## Dependency Closure

No new support row is needed. This reuses the existing
`citation-bibliography-csl-core` backlog row as a bounded Pandoc-local support
component and implements the smallest native PHP subset needed by this slice.
Out of scope remain CSL style XML term/locale processing, BibTeX/BibLaTeX
parsing, arbitrary style catalogs, Zotero/Mendeley integrations, online
lookups, and full citeproc parity.

Root harness: not run - isolated micro-slice.
