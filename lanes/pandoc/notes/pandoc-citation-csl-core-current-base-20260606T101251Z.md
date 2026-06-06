# Pandoc Citation/CSL Disambiguate Condition Handoff

Slice: `pandoc-citation-csl-core-current-base-20260606T101251Z`
Base: `f2b77d802e93bb0b73e3302173738b4dc3701217`
Date: 2026-06-06 UTC

## Behavior

This slice adds bounded native PHP support for CSL `cs:choose` branches with
`disambiguate="true"`. `CslStyle` now accepts and exposes the condition, and
`CitationCslProcessor` marks ambiguous author-year citation clusters plus
explicit `cslDisambiguate` citation markers before rendering. This lets
WordPress citation review packets render short-title disambiguators for
ambiguous local imports without invoking citeproc.

Source truth: CSL 1.0.2 conditional rendering includes a `disambiguate`
condition. This lane keeps the implementation bounded and does not attempt the
full citeproc disambiguation retry loop.

Reference: https://docs.citationstyles.org/en/v1.0.2/specification.html

## Evidence

- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note existed
  for this session outside stale historical handoff artifacts.
- Baseline focused command before edits:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1608 assertions, 0 failures`.
- Red-first focused command failed with the same test file at
  `1 test files, 1608 assertions, 1 failures` because `disambiguate` was not
  accepted as a CSL choose condition.
- Final focused command:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 1618 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-disambiguate-condition-handoff.php --self-test`
  passed.
- PHP syntax checks passed for changed PHP files:
  `lanes/pandoc/src/CslStyle.php`,
  `lanes/pandoc/src/CitationCslProcessor.php`,
  `lanes/pandoc/tests/CitationCslProcessorTest.php`, and
  `lanes/pandoc/examples/wordpress-citation-csl-disambiguate-condition-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1294 -> 1295`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`:
  `1708 -> 1709`.
- `mappedCitationCslCoreCases`: `10 -> 11`.
- Focused CitationCslProcessor coverage: `1608 -> 1618` assertions.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused
PHP test harness, and a lane-local WordPress handoff example.

Upstream runner parity remains blocked on a hydrated Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` plus Cabal project/package files
and Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch avoids prior Citation/CSL slices for date parts, uncertain-date
predicates, locator/page labels, et-al rendering, et-al-subsequent,
et-al-use-last, and bibliography subsequent-author substitution. It does not
change BibTeX/BibLaTeX parsing, note-style output, page-range collapsing, CSL
locale loading, or upstream runner evidence.

## Follow-Up

Keep disambiguate add-givenname/name expansion retry loops, bibliography
disambiguation, abbreviation-list lookup, note-style output, page-range
collapsing, broader locale/style XML behavior, and full citeproc/Pandoc runner
parity as separate bounded slices.
