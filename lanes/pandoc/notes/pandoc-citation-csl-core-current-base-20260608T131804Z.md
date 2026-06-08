# pandoc-citation-csl-core-current-base-20260608T131804Z

## Scope

Mapped one bounded Citation/CSL numeric text-variable gap on accepted base
`d6ec1fb5ef671b6ea22e454e765ca0d7b78582a5`: `cs:text` now applies the same
native number forms already used by `cs:number` for `first-reference-note-number`,
`locator`, `page`, `page-first`, `number`, `edition`, `volume`, `issue`,
`chapter-number`, `number-of-pages`, `number-of-volumes`, and
`collection-number`.

This keeps note-style first-reference links, locators, and bibliography page /
edition / issue review text source-faithful without invoking Pandoc, citeproc,
BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online
services, live provider tests, or live-service provider tests.

## Source Truth

- The lane already accepted bounded `cs:number` formatting for the same numeric
  variables and bounded `cs:text variable="citation-number" form="..."`
  formatting.
- The missing behavior was restricted to
  `CitationCslProcessor::renderTextVariableValue()`, which formatted
  `citation-number` text variables but returned raw values for other numeric
  variables when the style requested `form="ordinal"`, `form="long-ordinal"`,
  or `form="roman"`.
- The patch reuses the existing CSL number formatter and does not expand
  arbitrary non-numeric text-variable behavior.

## Evidence

- Rework check: no current non-stale
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  file existed for this lane.
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 2493 assertions, 1 failures` because repeated
  note output rendered `first-note 1 locator 9 edition 3` and bibliography
  output rendered raw `pages 12, 18 & 20. edition 3. number 2-4`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2495 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-numeric-text-form-handoff.php --self-test`
  passed with `wordpress-citation-csl-numeric-text-form-handoff self-test passed`.

## Status Delta

- `lane-status.json` `phpPass`: `1652 -> 1653`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2072 -> 2073`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`.
- Focused coverage delta: `+1` PHP PASS case and `+13` assertions in
  `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. This slice reuses `CslStyle` XML parsing,
`CitationCslProcessor` numeric rendering, `MarkdownReader` footnote/citation
parsing, `WordPressBlockWriter` footnote and bibliography output, the focused
lane PHP test harness, and the lane-local WordPress handoff example.

## Non-Overlap

This slice does not overlap accepted `cs:number` variable rendering,
`cs:text variable="citation-number"` formatting, citation-number collapse,
first-reference note-number assignment, CSL locator/page labels, page-range
formatting, date ordinals, BibTeX/BibLaTeX metadata handoffs, or non-CSL
Pandoc support-library rows. It only owns numeric `form` handling for
`cs:text` variables that are already supported by the native bounded
`cs:number` path.

## Root Harness

Not run - isolated micro-slice.
