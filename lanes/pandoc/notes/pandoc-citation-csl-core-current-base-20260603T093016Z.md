# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260603T093016Z`

Accepted base: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Behavior

- Added bounded native parsing for Pandoc bracketed citation clusters such as
  `[see @doe99, pp. 33-35; also @smith04, chap. 1]`.
- Preserves the accepted simple `[@id]` citation node path while adding
  `citation_group` inline nodes for multi-item clusters.
- Captures item prefixes, locators, suppress-author markers, forced curly
  locators, and curly-braced URL citation keys without invoking Pandoc,
  citeproc, BibTeX/Biber, bibliography managers, online lookups, or services.
- `CitationCslProcessor` now normalizes citation groups, records missing CSL
  IDs for reviewer follow-up, combines locator/suffix text, and renders
  author-date clusters through the existing bounded CSL JSON item handoff.
- Markdown and WordPress writers render processed `citation_group` nodes and
  keep source cluster text visible when the CSL processor has not run.

## Source Truth

- Upstream source truth is Pandoc citation syntax from the Pandoc User's Guide
  at <https://pandoc.org/demo/example33/8.20-citation-syntax.html>: normal
  citations use square brackets, distinct items are separated with semicolons,
  items may include prefixes, locators, and suffixes, `-@id` suppresses the
  author, `@id [locator]` remains the author-in-text form, and curly braces
  allow citation keys such as URLs.
- The local upstream cache was not available in this isolated worktree, so the
  implementation used the accepted lane manifest plus the official manual
  section as the bounded behavior source. The full Haskell runner was not
  executed.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 85 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  5 selected test files, 2671 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  emitted WordPress blocks with a rendered multi-item citation cluster, a
  suppress-author item, a visible missing source marker, a curly-braced URL
  citation key, and a CSL-derived Works Cited `<dl>`.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, simple author-date
single citations, YAML metadata placement, ZIP/OPC package primitives,
doctemplate pipes, Markdown table/link/footnote coverage, or HTML-reader
structure coverage. It closes only the bracketed citation-cluster parser and
rendering gap needed for richer Pandoc citation handoff.

## Dependency Closure

No new support component is needed. This reuses the accepted
`CitationCslProcessor`, Markdown AST, Markdown writer, and WordPress block
writer primitives. Out of scope remain CSL style XML/locale term processing,
BibTeX/BibLaTeX parsing, citation-position disambiguation, bibliography
placement in existing `refs` divs, note-style citeproc output, arbitrary style
catalogs, Zotero/Mendeley integrations, online lookups, and full citeproc
parity.

Root harness: not run - isolated micro-slice.
