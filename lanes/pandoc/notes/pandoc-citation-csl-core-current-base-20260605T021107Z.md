# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T021107Z`

Accepted base: `9ba7946319f5b9e185a6d4dfe01a2aa2d62b772b`

## Behavior

- Added bounded native CSL top-level `<macro name="...">` parsing to
  `CslStyle`.
- `CslStyle` now accepts `<text macro="...">` rendering references in
  citation, bibliography, and macro definitions, exposes parsed macros in the
  style summary, rejects undefined macro references, and rejects recursive
  macro chains before citation processing.
- `CitationCslProcessor` now renders macro-backed citation and bibliography
  layouts through the existing bounded `group`, `text`, `date`, and `names`
  evaluator.
- Macro-contained `<names>` elements preserve explicit local name rendering
  overrides such as delimiters, `et-al-min`, `et-al-use-first`,
  `initialize-with`, and `name-as-sort-order`; global citation/bibliography
  name fallbacks can also derive those options through macro references.
- Updated the WordPress citation CSL handoff example so reviewer-facing
  citation and bibliography output is driven by CSL macros without invoking
  citeproc, Pandoc, BibTeX, Biber, bibliography managers, online services, or
  Haskell runners.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated
  worktree; no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL style model already used by
  this lane: independent styles can define named macros, and rendering layouts
  can reference those macros with text macro references.
- This remains a bounded native PHP handoff, not full citeproc parity. It does
  not implement CSL `choose`, labels, numbers, rich date forms,
  disambiguation, citation-position logic, note-style output, external style
  catalogs, or full macro semantics beyond the already supported rendering
  elements.

## Evidence

- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 341 assertions, 1 failures`; the missing behavior
  was `CSL citation text element must declare exactly one variable, term, or
  value` for `<text macro="...">`.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  `1 test files, 359 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`:
  `19 test files, 5611 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`: `528`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style locale terms, bibliography layout affixes, sort
keys, direct layout rendering elements, BibTeX/BibLaTeX parsing,
crossref/xdata/set/related metadata, bracketed citation cluster parsing,
missing citation preservation, DOCX/ODT/EPUB package parsing, table geometry,
ZIP/OPC package primitives, doctemplate, YAML, archive compression, math/TeX,
legacy DOC/CFB, charset helpers, PDF handoff planning, or upstream-runner
dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
CSL `choose`, label and number rendering, richer date formatting,
disambiguation, citation-position logic, note-style output, style catalogs, and
full upstream runner hydration.
