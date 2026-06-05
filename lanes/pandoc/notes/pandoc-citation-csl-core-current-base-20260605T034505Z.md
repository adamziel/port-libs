# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T034505Z`

Accepted base: `c299e5bacc888df7894d35341e2c2d2d0cc94107`

## Behavior

- Added bounded native CSL citation-position support for citation-scope
  `choose` conditionals.
- `CslStyle` now parses and validates `position` attributes on `if` and
  `else-if` branches for `first`, `subsequent`, `ibid`, `ibid-with-locator`,
  and `near-note`. `near-note` remains parsed but intentionally never matches
  in this non-note bounded slice.
- `CitationCslProcessor` now annotates citations in document order before
  rendering so repeated items expose `cslPosition` and `cslPositionTests`.
  Bounded matching covers:
  - first reference to an item;
  - subsequent references;
  - ibid after immediately preceding same-item cites;
  - ibid-with-locator when the repeated item gains or changes a locator.
- The WordPress citation CSL handoff smoke now exercises repeated citation
  output through a CSL `position` branch without invoking citeproc.

## Source Truth

- CSL 1.0.2 specification, `position` condition:
  https://docs.citationstyles.org/en/v1.0.2/specification.html#choose
- This slice implements the bounded in-text position rules only. It does not
  implement note-distance `near-note`, disambiguation, citation-number/year
  suffix collapsing, note-style output, or full citeproc state.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 468 assertions, 0 failures`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 500 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `587`.
- Optional broader lane sample:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 6588 assertions, 1 failures`.
  - The failure is an existing `MarkdownReaderTest.php` structured table
    footer expectation about preserved `id` attributes in `<tfoot>` cells,
    outside the Citation/CSL files changed by this slice.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style locale terms, bibliography layout affixes, sort
keys, name rendering options, direct rendering elements, macro references,
variable/type choose conditionals, locator/page label rendering,
BibTeX/BibLaTeX parsing, crossref/xdata/set/related/translation metadata,
bracketed citation cluster parsing, missing citation preservation, DOCX/ODT/
EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
CSL number rendering, richer date forms, disambiguation, near-note
position behavior, note-style output, broader locator inference, external
style catalogs, and full upstream runner hydration.
