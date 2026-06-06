# Pandoc Citation/CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T013552Z`

Base accepted HEAD: `a81844785028d1e754b06f6a3382bda72627fbd0`

## Behavior

- Added bounded native CSL `cs:choose` `is-uncertain-date` condition support.
- `CslStyle` now preserves `is-uncertain-date` branch metadata for `cs:if` and
  `cs:else-if` in citation, bibliography, and macro rendering trees.
- `CitationCslProcessor` evaluates the condition against normalized CSL date
  metadata for `issued`, `accessed`, `original-date`, and `event-date`, reusing
  the existing uncertain-date flags already carried by CSL JSON and BibLaTeX
  handoff paths.
- Added a WordPress citation handoff example showing uncertain issued/accessed
  dates routed through CSL branches while stable dates stay in the fallback.

## Source Truth

- CSL 1.0.2 conditional rendering supports `is-uncertain-date` predicates in
  `cs:if` / `cs:else-if` branches.
- This slice implements only the bounded native condition contract. It does not
  implement full citeproc disambiguation, note-style output, `is-circa-date`,
  page-range collapsing, or full locale/style parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1466 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1481 assertions, 0 failures`.
  - Focused delta: `+1` PASS case and `+15` assertions.
  - `php lanes/pandoc/examples/wordpress-citation-csl-uncertain-date-handoff.php --self-test`
  - Result: `wordpress-citation-csl-uncertain-date-handoff self-test passed`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1145 -> 1146`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1596 -> 1597`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused `CitationCslProcessorTest.php`: `78 -> 79` PASS cases and
  `1466 -> 1481` assertions.

## Dependency Closure

No new support component is needed. This reuses native PHP `CslStyle`,
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
`is-numeric`, locator/page labels, number rendering, punctuation-in-quote,
et-al rendering, `delimiter-precedes-last`, subsequent-author substitution,
year-suffix disambiguation, citation collapse, BibTeX/BibLaTeX metadata,
table geometry, DOCX/ODT/EPUB, PDF, YAML, doctemplate, ZIP/OPC, archive
compression, charset/Unicode, XML/HTML5 DOM, legacy DOC/CFB, syntax
highlighting, or upstream-runner dependency audit work.

Follow-up CSL work should keep `is-circa-date`, note-style output, fuller
disambiguation, page-range collapsing, broader locale/style parity,
bibliography manager parity, and full upstream citeproc/Pandoc runner parity
as separate bounded slices.
