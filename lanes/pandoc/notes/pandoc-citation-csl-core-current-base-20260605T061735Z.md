# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T061735Z`

Accepted base: `c6ac5df0374dd36163d5c0e76bc3d26f21646bd2`

## Behavior

- Added bounded native CSL `cs:date-part` parsing and rendering for explicit
  month, day, and year parts.
- `CslStyle` now records date-part `form`, `prefix`, `suffix`,
  `range-delimiter`, `strip-periods`, and `text-case` metadata, and carries
  default English month terms for long and short month rendering.
- `CitationCslProcessor` now renders selected date parts with localized month
  terms, ordinal days, short years, date-part affixes, date-part text casing,
  and bounded date-range delimiters.
- The WordPress citation CSL handoff smoke now exposes date-part formatted
  bibliography output for reviewer calendars without invoking Pandoc,
  citeproc, bibliography managers, or online services.

## Source Truth

- CSL 1.0.2 specification, `Date-part`: `cs:date-part` supports `day`,
  `month`, and `year`; day forms include numeric, numeric-leading-zeros, and
  ordinal; month forms include long, short, numeric, and numeric-leading-zeros;
  year forms include long and short. Source:
  https://docs.citationstyles.org/en/v1.0.2/specification.html
- The same CSL section defines date-part affixes, `strip-periods` for month
  terms, text-case/formatting attributes, and `range-delimiter` selection from
  the largest differing date part in a range.
- This slice is intentionally bounded native PHP support. It does not implement
  localized `cs:date form="text|numeric"` locale format lookup,
  `limit-day-ordinals-to-day-1`, date range collapsing, rich font/display
  formatting, year-suffix disambiguation, note-style output, or full citeproc
  parity.

## Evidence

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 683 assertions, 0 failures`.
- Red-first after adding the focused date-part expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 697 assertions, 1 failures`; the new Markdown
    expectation needed escaping updates after the renderer produced the new
    date-part output.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 701 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - Result: both JSON files decode.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/
translation/legal/date-range/title/publication-detail/role metadata, bracketed
citation cluster parsing, missing citation preservation, CSL locale term
loading, bibliography layout affixes, sort keys, name rendering options,
direct text/date/group/names rendering, text-case transforms, macro
references, choose conditionals, locator/page label rendering, number
rendering, citation position conditionals, quotes/strip-periods for text/label
elements, `cs:name-part` formatting, DOCX/ODT/EPUB package parsing, table
geometry, ZIP/OPC package primitives, doctemplate, YAML, archive compression,
math/TeX, legacy DOC/CFB, charset helpers, PDF handoff planning, or
upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Full upstream Pandoc/citeproc runner parity remains
gated on hydrating the pinned Pandoc checkout and Cabal package/project
metadata; no external runner or bibliography tool was executed.
