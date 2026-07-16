# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T024127Z`

Accepted base: `5ed0aaa4d7c1c974c2a65ad595af51f1907f6f43`

## Behavior

- Added bounded native CSL `<choose>` parsing and rendering.
- `CslStyle` now preserves `choose` branches with `if`, `else-if`, and `else`
  children, validates branch order, validates `match` as `all`, `any`, or
  `none`, and records bounded `variable` and `type` conditions in the style
  summary.
- `CitationCslProcessor` now evaluates the first matching branch and reuses
  the existing bounded `group`, `text`, `date`, `names`, and macro rendering
  paths for branch contents.
- The WordPress citation handoff example now uses a macro-contained conditional
  source locator: DOI when present, URL when present, and explicit fallback
  audit text for local packets without stable source locators.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated worktree;
  no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL style model already used by
  the lane: rendering layouts and macros can contain conditional `choose`
  branches, and CSL conditions can test variables, item types, and `match`
  logic before rendering a branch.
- This remains a bounded native PHP handoff, not full citeproc parity. It does
  not implement labels, numbers, disambiguation, citation-position logic,
  note-style output, rich date forms, style catalogs, or unsupported CSL
  condition families.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 377 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 393 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5903 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `548`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style locale terms, bibliography layout affixes, sort
keys, name rendering options, direct rendering elements, macro references,
BibTeX/BibLaTeX parsing, crossref/xdata/set/related/translation metadata,
bracketed citation cluster parsing, missing citation preservation, DOCX/ODT/
EPUB package parsing, table geometry, ZIP/OPC package primitives, doctemplate,
YAML, archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF
handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
CSL labels, numbers, richer date forms, disambiguation, citation-position
logic, note-style output, external style catalogs, broader condition families,
and full upstream runner hydration.
