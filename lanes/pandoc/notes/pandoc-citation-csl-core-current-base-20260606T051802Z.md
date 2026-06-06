# Pandoc Citation/CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T051802Z`

Base accepted HEAD: `4f599e14426dfa0773ea9d27dafdea6025326f51`

## Behavior

- Added bounded native CSL approximate-date handling for `cs:choose`
  `is-uncertain-date` predicates.
- `CitationCslProcessor` now treats normalized CSL date metadata with
  `circa: true` as true for `is-uncertain-date`, matching the CSL approximate
  date contract instead of falling through as a stable date.
- `CslStyle` now supplies default `circa` long and short terms, including the
  short `c.` form used by bounded WordPress review styles.
- Added a WordPress citation handoff example showing approximate issued and
  accessed dates routed through CSL conditionals while stable dates remain on
  the fallback branch.

## Source Truth

- CSL 1.0.2 specifies that approximate dates test true for the
  `is-uncertain-date` conditional, and shows the `circa` term used before a
  rendered issued year:
  https://docs.citationstyles.org/en/v1.0.2/specification.html#approximate-dates
- The official CSL en-US locale includes `circa` and short `c.` term forms:
  https://github.com/citation-style-language/locales/blob/master/locales-en-US.xml

This slice implements only the bounded native conditional/term contract. It
does not implement full citeproc disambiguation, note-style output, locator
type conditionals, page-range collapsing, abbreviation-list lookup, or full
locale/style parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before the focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1505 assertions, 0 failures`.
- Red-first after adding the focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1500 assertions, 2 failures`.
  - Failing behavior: circa issued/accessed dates fell through stable branches
    instead of matching `is-uncertain-date`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1515 assertions, 0 failures`.
  - Focused delta: `+1` PASS case and `+10` net assertions.
  - `php lanes/pandoc/examples/wordpress-citation-csl-approximate-date-handoff.php --self-test`
  - Result: `wordpress-citation-csl-approximate-date-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1204 -> 1205`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1650 -> 1651`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused `CitationCslProcessorTest.php`: `80 -> 81` PASS cases and
  `1505 -> 1515` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `AstNode`, and
`WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, online service, or
live provider test was executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the
Haskell Tasty executables for `test-pandoc` and `test-pandoc-lua-engine`
before any non-mutating Cabal plan is marked ready.

## Non-Overlap

This does not repeat accepted Citation/CSL slices for CSL JSON item
normalization, source-access date/name metadata, date-part/date-form
rendering, text/name forms, macros, variable/type/position conditionals,
`is-numeric`, base uncertain-date metadata preservation, locator/page labels,
number rendering, punctuation-in-quote, et-al rendering,
`delimiter-precedes-last`, subsequent-author substitution, year-suffix
disambiguation, citation collapse, institution formatting, compact
family-given scripts, BibTeX/BibLaTeX metadata, table geometry, DOCX/ODT/EPUB,
PDF, YAML, doctemplate, ZIP/OPC, archive compression, charset/Unicode,
XML/HTML5 DOM, legacy DOC/CFB, syntax highlighting, or upstream-runner
dependency audit work.

Follow-up CSL work should keep disambiguate choose conditionals, locator type
conditionals, note-style output, page-range collapsing, fuller locale/style
parity, abbreviation-list lookup, bibliography disambiguation, and full
citeproc/Pandoc runner parity as separate bounded slices.
