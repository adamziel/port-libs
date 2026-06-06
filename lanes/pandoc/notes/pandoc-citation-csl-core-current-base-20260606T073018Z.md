# Pandoc Citation/CSL Current-Base Day Ordinal Handoff

Slice: `pandoc-citation-csl-core-current-base-20260606T073018Z`
Base accepted HEAD: `57f4d7834e2c36376702981396d2a1f58ee3649c`

## Behavior

Implemented bounded CSL locale `style-options limit-day-ordinals-to-day-1`
support. `CslStyle` now parses and validates the boolean locale option,
preserves it in the style summary, and `CitationCslProcessor` applies it when
rendering `date-part name="day" form="ordinal"`.

With the option enabled, first days still render as ordinal values such as
`1st`, while later days render as bare numeric days such as `2`. Without the
option, existing ordinal suffix behavior remains intact.

## Source-Truth Boundary

This ports the bounded CSL locale option contract into native PHP citation and
bibliography rendering. It does not run or shell out to Pandoc, citeproc,
BibTeX, Biber, Cabal, Haskell runners, external bibliography managers, online
sanitizers, online services, or live-service provider tests.

## Files

- `lanes/pandoc/src/CslStyle.php`
- `lanes/pandoc/src/CitationCslProcessor.php`
- `lanes/pandoc/tests/CitationCslProcessorTest.php`
- `lanes/pandoc/examples/wordpress-citation-csl-day-ordinal-handoff.php`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/pandoc-citation-csl-core-current-base-20260606T073018Z.md`

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1554 assertions, 0 failures`.
- Red-first: after adding the focused test, the same command failed with
  `1 test files, 1556 assertions, 1 failures` because
  `localeOptions.limitDayOrdinalsToDay1` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1565 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-day-ordinal-handoff.php --self-test`
  passed.
- PHP syntax checks passed for changed PHP files:
  `lanes/pandoc/src/CslStyle.php`,
  `lanes/pandoc/src/CitationCslProcessor.php`,
  `lanes/pandoc/tests/CitationCslProcessorTest.php`, and
  `lanes/pandoc/examples/wordpress-citation-csl-day-ordinal-handoff.php`.
- JSON validation: `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  passed.
- Diff whitespace: `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1243 -> 1244`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1686 -> 1687`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `10 -> 11`.
- Focused Citation/CSL coverage: `1554 -> 1565` assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and
lane-local test/example/status machinery. Full upstream runner parity remains
blocked on a hydrated Pandoc checkout and Cabal/Haskell test executable build
closure for the pinned upstream commit.

## Non-Overlap

This does not modify already accepted CSL date-part forms, punctuation-in-quote,
is-uncertain-date, et-al, subsequent-author, citation collapse, year-suffix,
BibTeX/BibLaTeX parsing, table geometry, YAML, DOCX, ODT, XML/HTML5 DOM,
math/TeX, PDF, archive, ZIP/OPC, charset, syntax-highlighting, or runner-audit
surfaces. Follow-up work should keep broader CSL locale inheritance, localized
ordinal terms, gendered ordinals, note-style output, citation-position
disambiguation, and full citeproc parity as separate slices.
