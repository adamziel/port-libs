# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T031345Z`

Accepted base: `6bd341fd72e174178a9f587b3cbd0324048f5760`

## Behavior

- Added bounded native CSL `label` rendering elements for citation locators
  and bibliography page-like variables.
- `CslStyle` now parses `label` elements, validates supported `variable`,
  `form`, and `plural` attributes, records them in style summaries, and uses
  term-form fallback for `short` and `symbol` label terms.
- `CitationCslProcessor` now passes citation context through custom CSL
  rendering so `<label variable="locator"/>` and `<text variable="locator"/>`
  can render the citation pinpointer without duplicating the older suffix path.
- `MarkdownReader` now preserves bounded locator metadata on bracketed
  citations as `locatorLabel` and `locatorValue` while keeping the existing
  `locator` string for compatibility.
- The WordPress citation CSL handoff smoke now uses a CSL locator macro and
  verifies that section locators remain available in parsed review packets.

## Source Truth

- Official CSL 1.0.2 spec: `cs:label` renders the term for `locator`, `page`,
  or number variables, only when the selected variable is non-empty; it supports
  `long`, `short`, and `symbol` forms plus contextual/forced plural behavior.
  Source: https://docs.citationstyles.org/en/stable/specification.html
- The same spec defines `locator` as a cite-specific pinpointer whose input
  label determines the term rendered by `cs:label`.
- This slice is intentionally bounded native PHP support. It does not implement
  full citeproc number rendering, page-range collapsing, `is-numeric`,
  `position`, note-style behavior, disambiguation, broad style catalogs, or
  full locator taxonomy parity.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 421 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 447 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6251 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `567`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style locale terms, bibliography layout affixes, sort
keys, name rendering options, direct rendering elements, macro references,
choose conditionals, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/
translation metadata, bracketed citation cluster parsing, missing citation
preservation, DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package
primitives, doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB,
charset helpers, PDF handoff planning, or upstream-runner dependency audit
work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
CSL number rendering, richer date forms, disambiguation, citation-position
logic, note-style output, broader locator inference, external style catalogs,
broader condition families, and full upstream runner hydration.
