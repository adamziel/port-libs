# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T041422Z`

Accepted base: `6d85ffdbe77273ccda143c6bc7574c543488d633`

## Behavior

- Added bounded native CSL `cs:number` parsing and rendering.
- `CslStyle` now accepts `number` rendering elements, validates supported
  number variables, validates `numeric`, `ordinal`, `long-ordinal`, and
  `roman` forms, exposes number elements in style summaries, and carries
  default ordinal/long-ordinal terms.
- `CitationCslProcessor` now renders numeric CSL variables with Pandoc/Citeproc
  style number behavior that is bounded to safe local PHP:
  - normalizes numeric separators: `2 - 4`, `2 , 3`, and `2&3`;
  - renders independent ordinal suffixes for extracted numbers;
  - renders long ordinals for 1 through 10;
  - renders lower-case roman numerals for 1 through 3999;
  - leaves non-numeric values such as `Appendix 2E` unchanged;
  - lets locale ordinal terms override built-in suffix defaults for this
    bounded path.
- Updated the WordPress citation CSL handoff example so reviewer bibliography
  output preserves an issue range as `nos. 2nd-4th` without invoking citeproc.

## Source Truth

- CSL 1.0.2 specification, `Number` rendering element:
  https://docs.citationstyles.org/en/stable/specification.html#number
- This slice implements the bounded native number-rendering contract only. It
  does not implement full CSL `is-numeric` condition handling, citation-number
  sequencing, page-range collapsing, gendered ordinals, formatting attributes,
  text-case transforms, note-style output, or full citeproc parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 519 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 534 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php | rg -c '^PASS '`
  - Result: `26`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php -l lanes/pandoc/src/CslStyle.php && php -l lanes/pandoc/src/CitationCslProcessor.php && php -l lanes/pandoc/tests/CitationCslProcessorTest.php && php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - Result: both JSON files decode.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL locale terms, bibliography layout affixes, sort keys,
name rendering options, direct text/date/group/names rendering, macro
references, variable/type/position choose conditionals, locator/page label
rendering, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/translation
metadata, bracketed citation cluster parsing, missing citation preservation,
DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
title casing/localized title-addon punctuation, richer date forms,
disambiguation, near-note position behavior, note-style output, broader style
catalogs, broader locator inference, and full upstream runner hydration.
