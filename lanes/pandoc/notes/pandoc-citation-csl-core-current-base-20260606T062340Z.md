# Pandoc Citation/CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T062340Z`
Base accepted HEAD: `9ad62bbe4e7fbecd3bd98f43017c2a6dc597e8c8`

## Behavior

- Added bounded native CSL `cs:choose` `locator` condition support.
- `CslStyle` now parses locator predicates on `cs:if` and `cs:else-if`
  branches, normalizes space/underscore variants to CSL hyphenated locator
  names, and rejects unsupported locator condition names at style-load time.
- `CitationCslProcessor` now evaluates locator conditions only in citation
  scope and only when the cite has a locator value, using the normalized
  Pandoc citation locator label from parsed Markdown or direct AST metadata.
- Added a WordPress citation handoff example showing page, chapter, section,
  paragraph, and fallback locator routing without invoking external citation
  tooling.

## Source Truth

The CSL 1.0.2 specification defines `locator` as a condition that tests whether
the cite locator matches one of the declared locator types, with multiple test
values separated by spaces:

- `https://docs.citationstyles.org/en/v1.0.2/specification.html`

This is a bounded native PHP support-library slice, not full citeproc/Pandoc
runner parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1542 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1554 assertions, 0 failures`.
  - Focused delta: `+1` PASS case and `+12` assertions.
  - `php lanes/pandoc/examples/wordpress-citation-csl-locator-condition-handoff.php --self-test`
  - Result: `wordpress-citation-csl-locator-condition-handoff self-test passed`.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1228 -> 1229`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1671 -> 1672`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused `CitationCslProcessorTest.php`: `83 -> 84` PASS cases and
  `1542 -> 1554` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`,
and lane-local manifest/status machinery.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, online service, or
live provider test was executed.

The upstream-runner dependency gate remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty executable builds for `test-pandoc`
and `test-pandoc-lua-engine` before any non-mutating upstream runner plan is
marked ready.

## Non-Overlap And Follow-Up

This does not repeat accepted Citation/CSL slices for CSL JSON normalization,
date/name metadata, date-part/date-form rendering, variable/type/position/
is-numeric/is-uncertain-date conditionals, locator/page label rendering,
number rendering, citation-number sequencing, year suffixes, citation collapse,
subsequent-author substitution, name formatting, institution formatting,
BibTeX/BibLaTeX metadata handoff, or non-CSL Pandoc support-library lanes.

Keep fuller locator inference such as figure/equation locators, disambiguate
conditionals, abbreviation-list institution lookup, note-style output,
page-range collapsing, and full citeproc/Pandoc runner parity as separate
bounded slices.

Root harness: not run - isolated micro-slice.
