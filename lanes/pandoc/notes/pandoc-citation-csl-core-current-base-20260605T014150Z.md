# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T014150Z`

Accepted base: `e329f66380efe3137a0b75bbe99bccdd2dc72f38`

## Behavior

- Extended bounded native `CslStyle` parsing to preserve direct CSL layout
  rendering elements: `group`, `text`, `date`, and `names`.
- `CitationCslProcessor` now evaluates those elements for citation and
  bibliography output, including element affixes, group delimiters, localized
  `term` text, title/publisher/container/page/DOI/URL variables, issued and
  accessed dates, `date-part` year/month/day selection, group suppression when
  all variable children are empty, and citation-scope title fallback for
  nameless URL-key sources.
- Updated the WordPress citation handoff smoke so reviewer-facing citation and
  bibliography output is produced by the bounded direct CSL layout path rather
  than only the hardcoded author-date fallback.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated
  worktree; no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL style model already used by
  the lane: citation and bibliography `layout` elements can contain rendering
  elements, `group` carries affixes/delimiters, `text` can render variables,
  terms, or literal values, `date` can render issued/accessed variables with
  date-parts, and `names` renders author/editor lists.
- This is not full citeproc. It does not implement macros, `choose`, labels,
  numbers, rich date forms, conditional disambiguation, citation-position
  logic, note styles, or external style catalogs.

## Evidence

- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 304 assertions, 1 failures`; the missing behavior
  was direct CSL rendering elements in `CslStyle::summary()`.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  `1 test files, 320 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style locale terms, bibliography layout affixes, sort
keys, name rendering options, BibTeX/BibLaTeX parsing, crossref/xdata/set/
related metadata, bracketed citation cluster parsing, missing citation
preservation, DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package
primitives, doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB,
charset helpers, PDF handoff planning, or upstream-runner dependency audit
work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
CSL macros, `choose`, labels, numbers, rich date formatting, disambiguation,
citation-position logic, note-style output, style catalogs, and full upstream
runner hydration.
