# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T011132Z`

Accepted base: `24d8a02fe41aad85f9cde8b9bb0e256f650c48c8`

## Behavior

- Extended bounded native `CslStyle` parsing to preserve direct CSL
  `layout`-scoped `<names>/<name>` rendering options for citation and
  bibliography output.
- Supports name delimiters, `and` join mode validation, `et-al-min`,
  `et-al-use-first`, `initialize-with`, and `name-as-sort-order` values.
- `CitationCslProcessor` now applies those options to author/editor citation
  labels and bibliography author strings while preserving the accepted default
  author-date output when no style name options are declared.
- The WordPress citation handoff smoke now proves reviewer-facing initialized
  bibliography names and bounded multi-author et-al labels without invoking
  citeproc, Pandoc, BibTeX, Biber, bibliography managers, online services, or
  Haskell runners.

## Source Truth

- Upstream Pandoc runner parity is still unavailable in this isolated
  worktree; no hydrated Pandoc/citeproc checkout or Cabal project is present.
- Source truth for this bounded slice is the CSL style model already used by
  the lane: rendering layouts can contain `names` and `name` elements, and
  those elements carry name-delimiter, initialization, sorting-order, and et-al
  threshold/use-first attributes.
- This does not attempt full CSL macro evaluation, delimiter-precedes-et-al,
  et-al-use-last, name form variants, locale date formatting, citation-position
  disambiguation, note-style citeproc output, external style catalogs, or full
  citeproc parity.

## Evidence

- Baseline before adding this test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 257 assertions, 0 failures`.
- Red-first check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 259 assertions, 1 failures`; the missing behavior
  was `nameRendering` in `CitationCslProcessor::cslStyleSummary()`.
- `php -l lanes/pandoc/src/CslStyle.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`:
  1 selected test file, 272 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`:
  19 selected test files, 5055 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`:
  `wordpress-citation-csl-handoff self-test passed`.
- `git diff --check -- lanes/pandoc`: no whitespace errors.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL style layout localization, bibliography layout affixes,
CSL sort keys, BibTeX/BibLaTeX parsing, BibTeX crossref/xdata inheritance,
bracketed citation cluster parsing, missing citation preservation,
DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No external dependency is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, Markdown reader/writer, and WordPress block
writer. Remaining citation closure is bounded follow-up work: full CSL macro,
text, date, and name rendering beyond the bounded style options here,
delimiter-precedes-et-al, et-al-use-last, name form variants, citation-position
logic, bibliography disambiguation, note-style output, external style catalogs,
and full upstream runner hydration.
